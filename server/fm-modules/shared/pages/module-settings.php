<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2013-2018 The facileManager Team                               |
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
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes module settings page                                          |
 | Author: Jon LaBass                                                      |
 +-------------------------------------------------------------------------+
*/

if (!currentUserCan(array('manage_settings', 'view_all'), $_SESSION['module'])) unAuth();

printHeader();
@printMenu();

include_once(ABSPATH . 'fm-modules/shared/classes/class_settings.php');

echo '<div id="body_container">
<div id="response" style="display: none;"></div>
	<h2>' . getPageTitle() . '</h2>
	' . $fm_module_settings->printForm() . '</div>' . "\n";

printFooter();

?>
