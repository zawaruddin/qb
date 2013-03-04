--TEST--
Use case: Ray Tracer
--FILE--
<?php

$folder = dirname(__FILE__);
$output = imagecreatetruecolor(512, 512);
$correct_path = "$folder/pbj/output/raytrace.correct.png";
$incorrect_path = "$folder/pbj/output/raytrace.incorrect.png";

define('NUM_SPHERES', 35);

class RayTracer {

	/** @var float32 */
	public $viewPlaneDistance = 2.0;
	
	/** @var float32[3] */
	public $lightPos = array(0.0, 2.0, -4.0);
	
	/** @var float32[3] */
	public $sphere0Position = array(0.0, 2.0, -10.0);
	
	/** @var float32 */
	public $sphere0Radius = 2.0;
	
	/** @var float32[3] */
	public $sphere0Color = array(0.8, 0.8, 0.8);
	
	/** @var float32[4] */
	public $sphere0Material = array(0.05, 0.1, 1.0, 1.0);
	
	const SPECULAR_EXPONENT			= 50.0;
	const MAX_RAY_SHOTS				= 4;
    
    /** @var float32[NUM_SPHERES][x, y, z] */
	public $spherePositions;
    
    /** @var float32[NUM_SPHERES] */
	public $sphereRadii;
    
    /** @var float32[NUM_SPHERES][r, g, b] */
	public $sphereColors;
    
    /** @var float32[NUM_SPHERES][ambient, diffuse, specular, reflectivity] */
	public $sphereMaterials;
    
	/** 
	* @engine qb 
	* 
	* @local uint32			$i
	* @local float32		$num
	*/
	private function initialize() {
	    // initialize our sphere parameters
        $this->spherePositions[0] = $this->sphere0Position;
        $this->sphereRadii[0] = $this->sphere0Radius;
        $this->sphereColors[0] = $this->sphere0Color;
        $this->sphereMaterials[0] = $this->sphere0Material;

		$this->spherePositions[1] = array(0.0, -1003.0, -8.0);
		$this->sphereRadii[1] = 1000.0;
		$this->sphereColors[1] = array(0.6, 0.6, 0.6);
		$this->sphereMaterials[1] = array(0.1, 0.8, 0.5, 0.5);
		
		// let's make a bunch of fakely random spheres
		for($i = 2; $i < NUM_SPHERES; $i ++) {
			$num = $i * 11;
			$this->spherePositions[$i]->x = sin($num / 5.0) * 6.0;
			$this->spherePositions[$i]->y = sin($num / 4.1) * 2.5;
			$this->spherePositions[$i]->z = -18.0 - sin($num / 3.1 + 1.2) * 10.0;
			$this->sphereRadii[$i] = pow(sin($num / 1.34 + 65.3) * 0.5 + 0.5, 3.0) * 1.0 + 0.2;
			$this->sphereColors[$i]->r = cos($num / 2.1 + 1.3) * 0.5 + 0.5;
			$this->sphereColors[$i]->g = cos($num / 0.1 + 1.3) * 0.5 + 0.5;
			$this->sphereColors[$i]->b = cos($num / 5.1 + 6.3) * 0.5 + 0.5;
			$this->sphereMaterials[$i]->ambient = 0.1;
			$this->sphereMaterials[$i]->diffuse = 0.7;
			$this->sphereMaterials[$i]->specular = 1.0;
			$this->sphereMaterials[$i]->reflectivity = pow(sin($num / 2.1 + 1.243) * 0.5 + 0.5, 5.0);
		}
	}

	/** 
	/* shootRay():  fires a ray from origin, toward dir
	/*              returns first intersection
	/* @engine qb
	 *
	 * @param float32[3]	$origin
	 * @param float32[3]	$dir
	 * @param bool			$hit
	 * @param float32[3]	$pos
	 * @param float32		$t
	 * @param uint32		$sphereNum
	 *
	 * @local float32 		$curT
	 * @local float32		$B
	 * @local float32		$C
	 * @local float32		$disc
	 * @local float32[3]	$sphereToOrigin
	 * @local uint32		$i
	 */
    private function shootRay($origin, $dir, &$hit, &$pos, &$t, &$sphereNum) {
		$hit = false;
		$t = 99999.0;
        
        // cycle through all spheres and find the smallest t>0 that we hit
		for($i = 0; $i < NUM_SPHERES; $i++) {
			$sphereToOrigin = $origin - $this->spherePositions[$i];
			$B = dot($sphereToOrigin, $dir);
			$C = dot($sphereToOrigin, $sphereToOrigin) - $this->sphereRadii[$i] * $this->sphereRadii[$i];
		
			$disc = $B * $B - $C;
			if($disc > 0.0) {
				$curT = -$B - sqrt($disc);
				if($curT > 0.0 && $curT < $t) {
					$sphereNum = $i;
					$t = $curT;
					$hit = true;
				}
			}
		}
		$pos = $origin + $dir * $t;
    }

	/** 
	 * generate():	generate raytraced image
	 *
	 * @engine qb
	 *
	 * @param image				$image
	 *
	 * @local uint32			$width
	 * @local uint32			$height
	 * @local uint32			$x
	 * @local uint32			$y	 
	 * @local float32[4]		$pixel
	 * @local float32[3]		$dst
	 * @local float32[x, y, z]	$origin
	 * @local float32[x, y, z]	$dir
     * @local float32[3]		$sphereHit		hit point relative to sphere
     * @local float32[3]		$n				surface normal
     * @local float32[3]		$lightVector	surface to light
     * @local float32			$lightVectorLen
     * @local float32[3]		$l				normalized light vector
	 * @local float32[3]		$lReflect		reflected off surface
	 * @local float32[3]		$dirReflect
	 * @local float32			$shadow
	 * @local float32[3]		$colorScale
	 * @local float32[3]		$sphereColor
	 * @local float32[ambient, diffuse, specular, reflectivity]	$sphereMaterial
	 * @local float32			$specular
	 * @local float32			$diffuse
	 * @local float32			$lightVal
	 * @local bool				$hit
	 * @local float32[3]		$hitPoint
	 * @local float				$t
	 * @local uint32			$sphereNum
	 * @local bool				$shadowTest
	 * @local float32[3]		$temp
	 * @local uint32			$temp2
	 * @local int32				$rayShots
	 * @local float32			$phi
	 * @local float32			$u
	 * @local float32			$v
	 */
	public function generate(&$image) {
		$this->initialize();
		
		// obtain dimension of output image
		$height = count($image);
		$width = count($image[0]);
		
		$pixel = array(0, 0, 0, 1);
		
		for($y = 0; $y < $height; $y++) {
			for($x = 0; $x < $width; $x++) {
				$dst = 0;
				$origin = 0;
				
		        // calculate direction vector for this pixel        
		        $dir->x = 2.0 * $x / $width - 1.0;
		        $dir->y = -2.0 * $y / $height + 1.0;
				$dir->z = -$this->viewPlaneDistance;
        
		        $colorScale = 1;
        		$rayShots = self::MAX_RAY_SHOTS;
        
				while($rayShots > 0 ) {
		            // let's make sure dir is properly normalized
					$dir = normalize($dir);
		            
					// INTERSECTION TEST
					// find the first sphere we intersect with
					$this->shootRay($origin, $dir, $hit, $hitPoint, $t, $sphereNum);
		                        
					if($hit) {
		                $sphereColor = $this->sphereColors[$sphereNum];
		                $sphereMaterial = $this->sphereMaterials[$sphereNum];
		                
						$sphereHit = $hitPoint - $this->spherePositions[$sphereNum];
						$n = $sphereHit / $this->sphereRadii[$sphereNum];				// normal at the point we hit
		                $lightVector = $this->lightPos - $hitPoint;						// hit point to light
						$lightVectorLen = length($lightVector);
		                $l = $lightVector / $lightVectorLen;
		                
						// SHADOW TEST
						// fire a ray from our hit position towards the light
						$this->shootRay($hitPoint, $l, $shadowTest, $temp, $t, $temp2);
		                
						if(!$shadowTest) {					// if we didn't hit anything, we can see the light
							$shadow = 1;
						} else if($t < $lightVectorLen)	{	// if we hit something before the light, we are in shadow
							$shadow = 0;
						}
		                
		                $diffuse = dot($l, $n);
		
						$lReflect = $l - 2.0 * $diffuse * $n;	// reflect the light vector
						$specular = dot($dir, $lReflect);
		                
		                $diffuse = max($diffuse, 0.0);
						$specular = pow(max($specular, 0.0), self::SPECULAR_EXPONENT);
		                
		                // ground checkboard texture
						if($sphereNum == 1) {
							$phi = acos(-dot(array(1.0, 0.0, 0.0), $n));
							$u = acos(dot(array(0.0, 0.0, 1.0), $n) / sin($phi)) / (2.0 * M_PI);
							$v = $phi / M_PI;
		                 
							// we could do sample_linear here to do some actual texturing. :)
							$sphereColor *= ((floor($u * 2000.0) + floor($v * 2000.0)) % 2.0 == 0.0) ? 0.5 : 1.0;
		                }
		                
						// finally, blend our color into this pixel
						$lightVal = $sphereMaterial->ambient + $shadow * (($diffuse * $sphereMaterial->diffuse) + ($specular * $sphereMaterial->specular));
						$dst += $colorScale * $lightVal * $sphereColor;
		                
		                // reflection
						if($sphereMaterial->reflectivity > 0.0) {
							$dirReflect = $dir - 2.0 * dot($dir, $n) * $n;		// reflect our view vector
							$dirReflect = normalize($dirReflect);
							
		                    // originate at our hit position, fire at reflected angle
							$origin = $hitPoint;
							$dir = $dirReflect;
							$rayShots--;
		                    
		                    // blend according to reflectivity
							$colorScale *= $sphereMaterial->reflectivity * $sphereColor;
		                } else {
							$rayShots = 0;
						}
					} else {
						$rayShots = 0;
					}
		        }
		        
		        $pixel[0] = $dst[0];
		        $pixel[1] = $dst[1];
		        $pixel[2] = $dst[2];
		        $image[$y][$x] = $pixel;
		    }
		}
	}
}

qb_compile();

$rayTracer = new RayTracer;
$rayTracer->generate($output);

imagepng($output, $incorrect_path);

?>