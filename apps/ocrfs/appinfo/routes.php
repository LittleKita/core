<?php

/**
* ownCloud - App Template Example
*
* @author Bernhard Posselt
* @copyright 2012 Bernhard Posselt nukeawhale@gmail.com 
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

namespace OCA\OCRFS;

use \OCA\AppFramework\App;
use \OCA\OCRFS\DependencyInjection\DIContainer;

/*************************
 * Define your routes here
 ************************/

/**
 * Normal Routes
 */
$this->create('ocrfs_fopen', '/fopen')->action(
	function($params){
		App::main('ReplicationController', 'fopen', $params, new DIContainer());
	}
);

$this->create('ocrfs_fread', '/fread')->action(
	function($params){
		App::main('ReplicationController', 'fread', $params, new DIContainer());
	}
);

$this->create('ocrfs_fwrite', '/fwrite')->action(
	function($params){
		App::main('ReplicationController', 'fwrite', $params, new DIContainer());
	}
);

$this->create('ocrfs_metadata', '/metadata')->action(
	function($params){
		App::main('ReplicationController', 'metadata', $params, new DIContainer());
	}
);

$this->create('ocrfs_fflush', '/fflush')->action(
	function($params){
		App::main('ReplicationController', 'fflush', $params, new DIContainer());
	}
);

$this->create('ocrfs_fstat', '/fstat')->action(
	function($params){
		App::main('ReplicationController', 'fstat', $params, new DIContainer());
	}
);

$this->create('ocrfs_feof', '/feof')->action(
	function($params){
		App::main('ReplicationController', 'feof', $params, new DIContainer());
	}
);

$this->create('ocrfs_fclose', '/fclose')->action(
	function($params){
		App::main('ReplicationController', 'fclose', $params, new DIContainer());
	}
);

$this->create('ocrfs_mkdir', '/mkdir')->action(
	function($params){
		App::main('ReplicationController', 'mkdir', $params, new DIContainer());
	}
);

$this->create('ocrfs_unlink', '/unlink')->action(
	function($params){
		App::main('ReplicationController', 'unlink', $params, new DIContainer());
	}
);

$this->create('ocrfs_rmdir', '/rmdir')->action(
	function($params){
		App::main('ReplicationController', 'rmdir', $params, new DIContainer());
	}
);

$this->create('ocrfs_urlstat', '/urlstat')->action(
	function($params){
		App::main('ReplicationController', 'urlstat', $params, new DIContainer());
	}
);

$this->create('ocrfs_opendir', '/opendir')->action(
	function($params){
		App::main('ReplicationController', 'opendir', $params, new DIContainer());
	}
);

$this->create('ocrfs_readdir', '/readdir')->action(
	function($params){
		App::main('ReplicationController', 'readdir', $params, new DIContainer());
	}
);

$this->create('ocrfs_rewinddir', '/rewinddir')->action(
	function($params){
		App::main('ReplicationController', 'rewinddir', $params, new DIContainer());
	}
);

$this->create('ocrfs_closedir', '/closedir')->action(
	function($params){
		App::main('ReplicationController', 'closedir', $params, new DIContainer());
	}
);

$this->create('ocrfs_backgroudsync', '/backgroudsync')->action(
	function($params){
		App::main('ReplicationController', 'startBackgroudSync', $params, new DIContainer());
	}
);

$this->create('ocrfs_hashlist', '/hashlist')->action(
	function($params){
		App::main('ReplicationController', 'getHashList', $params, new DIContainer());
	}
);

$this->create('ocrfs_syncfile', '/syncfile')->action(
	function($params){
		App::main('ReplicationController', 'syncFile', $params, new DIContainer());
	}
);
