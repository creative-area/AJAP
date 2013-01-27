<?php

/**
 * @Ajap
 * @Volatile
 */
class Simple_Volatile {
	
	/**
	 * @Init
	 */
	 public function init() {
	 	return '
	 	ok( true, "Volatile init" );
		ok( !!Simple && !( Simple.hasOwnProperty("Volatile")), "Not in Simple within init" );
	 	';
	 }
}
	
