--TEST--
Arctangent test
--FILE--
<?php

/**
 * A test function
 * 
 * @engine	qb
 * @param	float64	$[ab]
 * @param	float32	$[cd]
 * 
 * @return	void
 * 
 */
function test_function($a, $b, $c, $d) {
	echo atan($a), "\n";
	echo atan($b), "\n";
	echo atan($c), "\n";
	echo atan($d), "\n";
}

$a = 0;
$b = 1;
$c = 0.5;
$d = tan(M_PI / 2);
	
test_function($a, $b, $c, $d);

?>
--EXPECT--
0
0.78539816339745
0.4636476
1.570796
