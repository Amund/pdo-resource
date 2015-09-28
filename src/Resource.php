<?php
/*
The MIT License (MIT)

Copyright (c) 2010 Dimitri Avenel

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

class Resource {
	
	public static function get( PDO $pdo, $id ) {
		$resource = self::getMeta( $pdo, $id );
		if( !$resource )
			return FALSE;
		$resource['attributes'] = self::getAttributes( $pdo, $id );
		return $resource;
	}
		
	public static function getMeta( PDO $pdo, $id ) {
		$sql = '
			SELECT *
			FROM resource_meta m
			WHERE id=?
		';
		$stmt = $pdo->prepare( $sql );
		$stmt->execute( array( $id ) );
		$meta = $stmt->fetch( PDO::FETCH_ASSOC );
		if( !$meta )
			return FALSE;
		return $meta;
	}
	
	public static function getAttribute( PDO $pdo, $id, $attribute ) {
		$sql = '
			SELECT COALESCE(value_int,value_real,value_text) AS value
			FROM resource_attribute
			WHERE id=? AND attribute =?
			LIMIT 1
		';
		$stmt = $pdo->prepare( $sql );
		$stmt->execute( array( $id, $attribute ) );
		$attribute = $stmt->fetch( PDO::FETCH_COLUMN );
		if( !$attribute )
			return FALSE;
		return $attribute;
	}
	
	public static function getAttributes( PDO $pdo, $id ) {
		$sql = '
			SELECT attribute, COALESCE(value_int,value_real,value_text) AS value
			FROM resource_attribute
			WHERE id=?
		';
		$stmt = $pdo->prepare( $sql );
		$stmt->execute( array( $id ) );
		$attr = array();
		while( $row = $stmt->fetch( PDO::FETCH_ASSOC ) )
			$attr[$row['attribute']] = $row['value'];
		return $attr;
	}
	
	public static function setMeta( PDO $pdo, $id='', $type='', $parent=NULL ) {
		$sql = 'REPLACE INTO resource_meta (id,type,parent) VALUES (?,?,?)';
		$stmt = $pdo->prepare( $sql );
		$stmt->execute( array( $id, $type, $parent ) );
	}
	
	public static function setAttribute( PDO $pdo, $id, $attr, $value=NULL ) {
		if( $value === NULL ) {
			// remove
			$sql = '
				DELETE FROM resource_attribute
				WHERE id=? AND attribute=?
			';
			$stmt = $pdo->prepare( $sql );
			$stmt->execute( array( $id, $attr ) );
		} else {
			// replace
			$v = array(
				'id'=> $id,
				'attribute'=> $attr,
				'value_int'=> NULL,
				'value_real'=> NULL,
				'value_text'=> NULL,
			);
			// detect type
			$type = 'text';
			if( is_numeric( $value ) )
				$type = ( strpos( $value, '.' ) === FALSE ? 'int' : 'real' );
			$v['value_'.$type] = $value;
			
			$sql = '
				REPLACE INTO resource_attribute
				( id, attribute, value_int, value_real, value_text )
				VALUES
				( :id, :attribute, :value_int, :value_real, :value_text )
			';
			$stmt = $pdo->prepare( $sql );
			$stmt->execute( $v );
		}
	}
	
	public static function setAttributes( PDO $pdo, $id, $attributes=NULL ) {
		if( !is_array( $attributes ) ) {
			// remove
			$sql = '
				DELETE FROM resource_attribute
				WHERE id=?
			';
			$stmt = $pdo->prepare( $sql );
			$stmt->execute( array( $id ) );
		} else {
			// replace
			foreach( $attributes as $k=>$v )
				self::setAttribute( $pdo, $id, $k, $v );
		}
	}
	
	public static function getParent( PDO $pdo, $id ) {
		$r = self::getMeta( $pdo, $id );
		if( !$r || !is_numeric( $r['parent'] ) )
			return FALSE;
		$parent = self::get( $pdo, $r['parent'] );
		if( !$parent )
			return FALSE;
		return $parent;
	}
	
	public static function createTables( PDO $pdo ) {
		$sql = array();
		switch( $pdo->getAttribute( PDO::ATTR_DRIVER_NAME ) ) {
			case 'sqlite':
				$sql[] = '
					CREATE TABLE IF NOT EXISTS `resource_meta` (
						`id` INTEGER PRIMARY KEY,
						`type` TEXT,
						`parent` INTEGER
					)
				';
				$sql[] = '
					CREATE TABLE IF NOT EXISTS `resource_attribute` (
						`id` INTEGER,
						`attribute` TEXT,
						`value_int` INTEGER,
						`value_real` REAL,
						`value_text` TEXT,
						PRIMARY KEY (`id`, `attribute`)
					)
				';
				break;
			case 'mysql':
				$sql[] = '
					CREATE TABLE IF NOT EXISTS `resource_meta` (
						`id` INT(11) NOT NULL AUTO_INCREMENT,
						`type` VARCHAR(50) NULL DEFAULT NULL,
						`parent` INT(11) NULL DEFAULT NULL,
						PRIMARY KEY (`id`),
						INDEX `id_parent` (`parent`)
					)
					ENGINE=InnoDB
				';
				$sql[] = '
					CREATE TABLE IF NOT EXISTS `resource_attribute` (
						`id` INT(11) NOT NULL,
						`attribute` VARCHAR(50) NOT NULL,
						`value_int` INT(11) NULL DEFAULT NULL,
						`value_real` FLOAT NULL DEFAULT NULL,
						`value_text` TEXT NULL,
						UNIQUE (`id`, `attribute`)
					)
					COLLATE="utf8_unicode_ci"
					ENGINE=InnoDB
				';
				break;
			default:
				throw new Exception( 'Unsupported PDO driver' );
		}
		foreach( $sql as $q )
			$pdo->exec( $q );
	}
	
}


//$pdo = new PDO( 'mysql:host=localhost;dbname=noop_cms;charset=utf8','test','test' );
//$pdo = new PDO( 'sqlite:'.__DIR__.'/db.sqlite' );

//$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
//$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//Resource::createTables( $pdo );

//Resource::setMeta( $pdo, 1, 'article' );
//Resource::setAttributes( $pdo, 1, array('title'=>'My title','content'=>'My content' ) );
//Resource::setMeta( $pdo, 2, 'tags', 1 );
//Resource::setAttributes( $pdo, 2, array( 'tag 1', 'tag 2' ) );
//Resource::setAttributes( $pdo, 3, array( 5.0, 5 ) );
//Resource::setAttributes( $pdo, 3, array( '5.0', '5' ) );

//print_r( Resource::getMeta( $pdo, 1 ) );
//echo Resource::getAttribute( $pdo, 1, 'title' );
//print_r( Resource::getAttributes( $pdo, 1 ) );
//print_r( Resource::get( $pdo, 2 ) );
//print_r( Resource::getParent( $pdo, 2 ) );
