--TEST--
Error reporting test (syntax error in type declaration)
--FILE--
<?php

/**
 * A test function
 * 
 * @engine	qb
 * @local	int332	$a
 * 
 * @return	void
 * 
 */


 
function test_function() {
}

?>
--EXPECTREGEX--
.*line 7.*