<?php


class Config {
	function Get($name) {
		$params = array(

				# Database credentials
				'DB_HOST' => 'localhost',
				'DB_NAME' => 'asterisk',
				'DB_USER' => 'root',
				'DB_PASS' => 'abc123!!!',
			);

		return $params[$name];
	}
}
