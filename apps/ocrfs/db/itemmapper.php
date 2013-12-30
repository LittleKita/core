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

namespace OCA\OCRFS\Db;

use \OCA\AppFramework\Core\API;
use \OCA\AppFramework\Db\Mapper;


class ItemMapper extends Mapper {


	/**
	 * @param API $api: Instance of the API abstraction layer
	 */
	public function __construct($api){
		parent::__construct($api, 'ocrfs_items');
	}


	/**
	 * Finds an item by id
	 * @throws DoesNotExistException: if the item does not exist
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 * @return the item
	 */
	public function find($id){
		$sql = 'SELECT * FROM `' . $this->getTableName() . '` WHERE `id` = ?';
		$params = array($id);
		
		$row = $this->findOneQuery($sql, $params);
		
		$item = new Item();
		$item->fromRow($row);
		return $item;
	}


	/**
	 * Finds an item by user id
	 * @param string $userId: the id of the user that we want to find
	 * @throws DoesNotExistException: if the item does not exist
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 * @return the item
	 */
	public function findByUserId($userId){
		$sql = 'SELECT * FROM `' . $this->getTableName() . '` WHERE `user` = ?';
		$params = array($userId);

		$row = $this->findOneQuery($sql, $params);
		
		$item = new Item();
		$item->fromRow($row);
		return $item;
	}


	/**
	 * Finds all Items
	 * @return array containing all items
	 */
	public function findAll(){
		$sql = 'SELECT * FROM `' . $this->getTableName() . '`';

		$result = $this->execute($sql);
		
		$entityList = array();

		while($row = $result->fetchRow()){
			$item = new Item();
			$item->fromRow($row);
			array_push($entityList, $item);
		}

		return $entityList;
	}


}
