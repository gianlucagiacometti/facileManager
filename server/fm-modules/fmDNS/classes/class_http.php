<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

class fm_module_http {
	
	/**
	 * Displays the http endpoint list
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $result Database query array
	 * @param integer $page Page number
	 * @param integer $total_pages Total number of pages
	 * @return none
	 */
	function rows($result, $page, $total_pages) {
		global $fmdb, $__FM_CONFIG;
		
		$num_rows = $fmdb->num_rows;
		$results = $fmdb->last_result;

		$start = $_SESSION['user']['record_count'] * ($page - 1);
		echo displayPagination($page, $total_pages);

		$table_info = array(
						'class' => 'display_results',
						'id' => 'table_edits',
						'name' => 'http'
					);

		$title_array = array(array('title' => __('Endpoint Name')), array('title' => __('Options')), array('title' => _('Comment'), 'class' => 'header-nosort'));
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$title_array[] = array('title' => __('Actions'), 'class' => 'header-actions header-nosort');
		}

		echo displayTableHeader($table_info, $title_array);
		
		if ($result) {
			$y = 0;
			for ($x=$start; $x<$num_rows; $x++) {
				if ($y == $_SESSION['user']['record_count']) break;
				echo '</tbody><tbody>';
				$this->displayRow($results[$x], $num_rows);
				$y++;
			}
		}
			
		echo "</tbody>\n</table>\n";
		if (!$result) {
			printf('<p id="table_edits" class="noresult" name="http">%s</p>', __('There are no HTTP endpoints defined.'));
		}
	}

	/**
	 * Adds the new endpoint
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return string|array|boolean
	 */
	function add($post) {
		global $fmdb, $__FM_CONFIG;

		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'http'";
		$result = $fmdb->query($query);
		foreach ($fmdb->last_result as $k => $def) {
			$include_sub_configs[] = $def->def_option;
		}

		/** Validate entries */
		$post = $this->validatePost($post, $include_sub_configs);
		if (!is_array($post)) return $post;

		/** Insert the parent */
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = null;
		
		$include = array('cfg_isparent', 'cfg_type', 'server_serial_no', 'cfg_name', 'cfg_data', 'cfg_comment');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$clean_data = ($key == 'cfg_data') ? sanitize($data, '-') : sanitize($data);
				if ($key == 'cfg_data' && empty($clean_data)) return __('No HTTP endpoint name defined.');
				$sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
				if ($key == 'cfg_comment') {
					$log_message[] = sprintf('Comment: %s', $clean_data);
				}
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_insert $sql_fields VALUES ($sql_values)";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the record because a database error occurred.'), 'sql');
		}

		$endpoint_name = $post['cfg_data'];

		/** Insert child configs */
		$post['cfg_isparent'] = 'no';
		$post['cfg_parent'] = $fmdb->insert_id;
		$post['cfg_name'] = '';
		unset($post['cfg_comment']);
		$include[] = 'cfg_parent';
		
		$sql_insert = "INSERT INTO `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config`";
		$sql_fields = '(';
		$sql_values = '(';
		
		$i = 1;
		foreach ($include_sub_configs as $handler) {
			$post['cfg_name'] = $handler;
			$post['cfg_data'] = $post[$handler];
			
			foreach ($post as $key => $data) {
				if (!in_array($key, $include)) continue;
				$clean_data = sanitize($data);
				if ($i) $sql_fields .= $key . ', ';
				$sql_values .= "'$clean_data', ";
			}
			$i = 0;
			$sql_values = rtrim($sql_values, ', ') . '), (';

			if ($post['cfg_data']) {
				$log_message[] = sprintf('%s: %s', $post['cfg_name'], $post['cfg_data']);
			}
		}
		$sql_fields = rtrim($sql_fields, ', ') . ')';
		$sql_values = rtrim($sql_values, ', (');
		
		$query = "$sql_insert $sql_fields VALUES $sql_values";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not add the record because a database error occurred.'), 'sql');
		}
		
		$log_message = sprintf("Added HTTP Endpoint:\nName: %s\n%s", $endpoint_name, join("\n", (array) $log_message));
		addLogEntry($log_message);
		return true;
	}
	
	
	/**
	 * Updates the selected endpoint
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @return string|array|boolean
	 */
	function update($post) {
		global $fmdb, $__FM_CONFIG;
		
		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'http'";
		$result = $fmdb->query($query);
		foreach ($fmdb->last_result as $k => $def) {
			$include_sub_configs[] = $def->def_option;
		}

		/** Validate entries */
		$post = $this->validatePost($post, $include_sub_configs);
		if (!is_array($post)) return $post;

		$endpoint_name = $post['cfg_data'];
		unset($post['cfg_name']);

		/** Update the parent */
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		$sql_values = null;
		
		$include = array('cfg_isparent', 'cfg_data', 'cfg_type', 'cfg_comment');
		
		/** Insert the category parent */
		foreach ($post as $key => $data) {
			if (in_array($key, $include)) {
				$clean_data = ($key == 'cfg_data') ? sanitize($data, '-') : sanitize($data);
				if ($key == 'cfg_data' && empty($clean_data)) return __('No HTTP endpoint name defined.');
				$sql_values .= "$key='$clean_data', ";
				if ($key == 'cfg_comment') {
					$log_message[] = sprintf('Comment: %s', $clean_data);
				}
			}
		}
		$sql_values = rtrim($sql_values, ', ');
		
		$query = "$sql_start $sql_values WHERE cfg_id={$post['cfg_id']} LIMIT 1";
		$result = $fmdb->query($query);
		
		if ($fmdb->sql_errors) {
			return formatError(__('Could not update the item because a database error occurred.'), 'sql');
		}

		/** Update config children */
		$include = array_diff(array_keys($post), $include, array('cfg_id', 'action', 'account_id', 'view_id', 'tab-group-1'));
		$sql_start = "UPDATE `fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}config` SET ";
		
		foreach ($include as $handler) {
			$sql_values = null;
			$child['cfg_name'] = $handler;
			$child['cfg_data'] = $post[$handler];
			
			foreach ($child as $key => $data) {
				$clean_data = sanitize($data);
				$sql_values .= "$key='$clean_data', ";
			}
			$sql_values = rtrim($sql_values, ', ');
			
			if ($child['cfg_data']) {
				$log_message[] = sprintf('%s: %s', $child['cfg_name'], $child['cfg_data']);
			}

			$query = "$sql_start $sql_values WHERE cfg_parent={$post['cfg_id']} AND cfg_name='$handler' LIMIT 1";
			$result = $fmdb->query($query);

			if ($fmdb->sql_errors) {
				return formatError(__('Could not update the item because a database error occurred.'), 'sql');
			}
		}
		
		$log_message = sprintf("Updated HTTP Endpoint:\nName: %s\n%s", $endpoint_name, join("\n", (array) $log_message));
		addLogEntry($log_message);

		return true;
	}
	
	
	/**
	 * Deletes the selected http endpoint
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param integer $id Item ID
	 * @param integer $server_serial_no Server serial number
	 * @param string $type
	 * @return string|array|boolean
	 */
	function delete($id, $server_serial_no, $type) {
		global $fmdb, $__FM_CONFIG;
		
		/** Are there any corresponding configs? */
		if (getConfigAssoc($id, 'http')) {
			return formatError(__('This item is still being referenced and could not be deleted.'), 'sql');
		}

		$tmp_name = getNameFromID($id, 'fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', 'cfg_', 'cfg_id', 'cfg_data');

		/** Delete associated children */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_parent') === false) {
			return formatError(__('This item could not be deleted because a database error occurred.'), 'sql');
		}
		
		/** Delete item */
		if (updateStatus('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', $id, 'cfg_', 'deleted', 'cfg_id') === false) {
			return formatError(__('This item could not be deleted because a database error occurred.'), 'sql');
		} else {
			setBuildUpdateConfigFlag($server_serial_no, 'yes', 'build');
			addLogEntry(sprintf(__("HTTP endpoint '%s' was deleted."), $tmp_name));
			return true;
		}
	}


	/**
	 * Displays the result row
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param object $row Row array
	 * @param integer $num_rows Number of rows
	 */
	function displayRow($row, $num_rows) {
		global $__FM_CONFIG, $fmdb;
		
		if ($row->cfg_status == 'disabled') $class[] = 'disabled';
		
		$server_serial_no .= $row->server_serial_no ? '&server_serial_no=' . $row->server_serial_no : null;
		if (currentUserCan('manage_servers', $_SESSION['module'])) {
			$edit_status = '<td id="row_actions">';
			if (!getConfigAssoc($row->cfg_id, 'http')) {
				$edit_status .= '<a class="edit_form_link" href="#">' . $__FM_CONFIG['icons']['edit'] . '</a>';
				$edit_status .= '<a class="status_form_link" href="#" rel="';
				$edit_status .= ($row->cfg_status == 'active') ? 'disabled' : 'active';
				$edit_status .= '">';
				$edit_status .= ($row->cfg_status == 'active') ? $__FM_CONFIG['icons']['disable'] : $__FM_CONFIG['icons']['enable'];
				$edit_status .= '</a>';
				$edit_status .= '<a href="#" class="delete">' . $__FM_CONFIG['icons']['delete'] . '</a>';
			}
			$edit_status .= '</td>';
		} else {
			$edit_status = null;
		}
		
		$http_endpoint_name = $row->cfg_data;
		$comments = nl2br($row->cfg_comment);

		basicGetList('fm_' . $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'config', array('cfg_name'), 'cfg_', 'AND cfg_type="http" AND cfg_parent="' . $row->cfg_id . '" AND cfg_isparent="no"', null, false);
		foreach ($fmdb->last_result as $record) {
			if ($record->cfg_data) {
				(string) $options .= sprintf('<b>%s</b> %s<br />', $record->cfg_name, $record->cfg_data);
			}
		}

		if ($class) $class = 'class="' . join(' ', $class) . '"';

		echo <<<HTML
		<tr id="$row->cfg_id" name="http" $class>
			<td>$http_endpoint_name</td>
			<td>$options</td>
			<td>$comments</td>
			$edit_status
		</tr>
HTML;
	}

	/**
	 * Displays the form to add/edit http types
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $data Posted array
	 * @param string $action Add or Edit
	 * @param string $cfg_type Configuration type
	 * @param integer $cfg_type_id
	 * @return string
	 */
	function printForm($data = '', $action = 'add', $cfg_type = 'http', $cfg_type_id = null) {
		global $fmdb, $__FM_CONFIG, $fm_dns_zones;
		
		$cfg_id = 0;
		$cfg_name = $cfg_data = $cfg_comment = null;
		$server_serial_no = (isset($_REQUEST['request_uri']['server_serial_no']) && (intval($_REQUEST['request_uri']['server_serial_no']) > 0 || $_REQUEST['request_uri']['server_serial_no'][0] == 'g')) ? sanitize($_REQUEST['request_uri']['server_serial_no']) : 0;

		
		if (!empty($_POST) && !array_key_exists('is_ajax', $_POST)) {
			if (is_array($_POST))
				extract($_POST);
		} elseif (@is_object($data[0])) {
			extract(get_object_vars($data[0]));
		}

		/** Get child elements */
		$query = "SELECT * FROM fm_{$__FM_CONFIG[$_SESSION['module']]['prefix']}functions WHERE def_function = 'http'";
		$result = $fmdb->query($query);
		foreach ($fmdb->last_result as $k => $def) {
			$auto_fill_children[] = $def->def_option;
			$valid_types = trim(str_replace(array('(', ')'), '', $def->def_type));
				
			switch ($valid_types) {
				case 'integer':
					$form_addl_html[$def->def_option] = 'maxlength="5" style="width: 5em;" onkeydown="return validateNumber(event)"';
					break;
				default:
					$form_addl_html[$def->def_option] = null;
			}
		}
		
		$child_config = getConfigChildren($cfg_id, $cfg_type, array_fill_keys($auto_fill_children, null));
		foreach ((array) $child_config as $k => $v) {
			$child_config[$k] = str_replace(array('"', "'"), '', (string) $v);
			$child_config_form[] = sprintf('
				<tr>
					<th width="33&#37;" scope="row"><label for="%s">%1$s</label></th>
					<td width="67&#37;"><input name="%2$s" id="%2$s" type="text" value="%3$s" %4$s/></td>
				</tr>', str_replace('-', ' ', $k), $k, $child_config[$k], $form_addl_html[$k]
			);
		}
		
		/** Get field length */
		$http_name_length = getColumnLength('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'config', 'cfg_name');

		$popup_title = $action == 'add' ? __('Add HTTP Endpoint') : __('Edit HTTP Endpoint');
		$popup_header = buildPopup('header', $popup_title);
		$popup_footer = buildPopup('footer');
		
		$return_form = sprintf('<form name="manage" id="manage" method="post" action="">
		%s
			<input type="hidden" name="action" value="%s" />
			<input type="hidden" name="cfg_id" value="%d" />
			<input type="hidden" name="view_id" value="%d" />
			<input type="hidden" name="server_serial_no" value="%s" />
			<div id="tabs">
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-1" checked />
					<label for="tab-1">%s</label>
					<div id="tab-content">
						<table class="form-table">
							<tr>
								<th width="33&#37;" scope="row"><label for="cfg_data">%s</label></th>
								<td width="67&#37;"><input name="cfg_data" id="cfg_data" type="text" value="%s" size="40" placeholder="%s" maxlength="%d" /></td>
							</tr>
							<tr>
								<th width="33&#37;" scope="row"><label for="cfg_comment">%s</label></th>
								<td width="67&#37;"><textarea id="cfg_comment" name="cfg_comment" rows="4" cols="30">%s</textarea></td>
							</tr>
						</table>
					</div>
				</div>
				<div id="tab">
					<input type="radio" name="tab-group-1" id="tab-2" />
					<label for="tab-2">%s</label>
					<div id="tab-content">
						<table class="form-table">
							%s
						</table>
					</div>
				</div>
			</div>
		%s
		</form>
		<script>
			$(document).ready(function() {
				$("#manage select").select2({
					width: "200px",
					minimumResultsForSearch: 10,
					allowClear: true
				});
			});
		</script>',
				$popup_header, $action, $cfg_id, $cfg_type_id, $server_serial_no,
				__('Basic'),
				__('Endpoint Name'), $cfg_data, __('http-name'), $http_name_length,
				_('Comment'), $cfg_comment,
				__('Advanced'),
				implode("\n", $child_config_form),
				$popup_footer,
			);

		return $return_form;
	}
	
	
	/**
	 * Validates the submitted form
	 *
	 * @since 6.0
	 * @package facileManager
	 * @subpackage fmDNS
	 *
	 * @param array $post Posted array
	 * @param array $include_sub_configs Array of sub configs to validate
	 * @return array|boolean
	 */
	function validatePost($post, $include_sub_configs) {
		global $fmdb, $__FM_CONFIG;
		
		$post['account_id'] = $_SESSION['user']['account_id'];
		$post['cfg_isparent'] = 'yes';
		$post['cfg_name'] = 'http-endpoint';
		$post['cfg_data'] = trim($post['cfg_data']);
		$post['cfg_comment'] = trim($post['cfg_comment']);
		$post['cfg_type'] = 'http';
		$post['cfg_id'] = sanitize($post['cfg_id']);

		include_once(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_options.php');
		
		foreach ($post as $key => $val) {
			if (!$val) continue;
			if (in_array($key, $include_sub_configs)) {
				$post2['cfg_name'] = $key;
				$post2['cfg_data'] = $val;
				$def_check = $fm_module_options->validateDefType($post2);
				if (!is_array($def_check)) {
					return $def_check;
				} else {
					$post[$key] = $def_check['cfg_data'];
				}
			}
		}

		return $post;
	}

}

if (!isset($fm_module_http))
	$fm_module_http = new fm_module_http();

?>
