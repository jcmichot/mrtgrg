<?php

/*

mrtgrg.php (MRTG RRD GRAPH)

version 1.0 jc@michot.fr

The purpose of this small program is to generate html pages on the fly
from rrd files generated by MRTG. 

html and images are built in realtime, this mean no .html or .png file
stored on server. For a fast display with many small image, the html 
include inline png data.

This program is a rainy saturday projet, but it may be useful to some 
people so i decide to publish it as a beerware.

 * "THE BEER-WARE LICENSE" 
 * jc@michot.fr write this file. As long as you retain this notice 
 * you can do whatever you want with this stuff.
 * If we meet some day, and you think this stuff is worth it, 
 * you can buy me a beer in return.

You only need to configure below the $mrtgconfigfiles with one or
several absolute path and filename for your mrtg configuration file.
(theses files must be readable by your web user running php script)

Syntax example:

 rrdmrtg.php
 rrdmrtg.php?cfg=mrtg.cfg&target=port24
 rrdmrtg.php?cfg=mrtg.cfg&target=port24&png=weekly

 To manualy force width &| height 
 (this can also be configured inside mrtg config file):
 
 rrdmrtg.php?cfg=homebridge.cfg&target=power-home&png=weekly&width=1400&height=300

A new option is available in your MRTG config file:

    Rrd*Graph[ TARGET ]: 2waygraph,forcearea,forceday

    forceline: force display line/s
    forcearea: force display area/s
    forceday: do not use week number in month graph
    2waygraph: graph in/ou in 2 way (up/down a 0 line) (enable forcearea)
    autoscale: man rrdgraph
    autoscale-max: man rrdgraph
    autoscale-min: man rrdgraph

    Rrd*HtmlHead[ TARGET ]: /usr/local/www/data/htmlfiletoinclude.html

All test has been done with:
 FreeBSD and pkg mod_php74-7.4.27 php74-gd-7.4.23 php74-pecl-rrd-2.0.1_1 

Note: i'm also using influxdb, telegraf, grafana,
      but i still use mrtg and rrdtool for large monitoring.

*/

$mrtgconfigfiles = array (

	'/usr/local/etc/mrtg/mrtg.cfg',
	/*
	'/usr/local/etc/mrtg/homebridge.cfg',
	'/usr/local/etc/mrtg/livebox-pm.cfg',
	'/usr/local/etc/mrtg/perso.cfg',
	'/usr/local/etc/mrtg/rgw.cfg',
	*/

	);

$display_main_dw = 1;



function read_mrtg_config_file( $configfile )
{
	$aconfig = array();

	// https://oss.oetiker.ch/mrtg/doc/mrtg-reference.en.html

	$asearch = array (

		// global file config

		'LogFormat:',	// Setting LogFormat to 'rrdtool' in your mrtg.cfg file enables rrdtool mode
		'WorkDir:',		// WorkDir specifies where the logfiles and the webpages should be created.
		'HtmlDir:',		// HtmlDir specifies the directory where the html (or shtml, but we'll get on to those later) lives.
		'ImageDir:',	// ImageDir specifies the directory where the images live. They should be under the html directory.
		'IconDir:',		// If you want to keep the mrtg icons in someplace other than the working
		'LogDir:',		// LogDir specifies the directory where the logs are stored. 
		'Refresh:',		// How many seconds apart should the browser be instructed to reload the page? default is 300 seconds (5 minutes).
		'Interval:',	// How often do you call mrtg? The default is 5 minutes.
		'Language:',	// Switch output format to the selected Language 

		// ignore
		'MaxAge:','WriteExpires:','EnableIPv6:','PathAdd:','RRDCached:','NoSpaceChar:',

		// my own options for special graph
		'Rrd*Graph[', 'Rrd*HtmlHead[',

		// targets we handle
		'Title[', 'PageTop[', 'Factor[', 'YLegend[', 'LegendI[', 'LegendO[',
		'Weekformat[', 'Xsize[', 'Ysize[', 'XZoom[', 'YZoom[',

		// target, may be usefull (or not) for futur version...
		'MaxBytes[', 'RouterUptime[', 'RouterName[',
		'MaxBytes1[', 'MaxBytes2[', 'PageFoot[', 'AddHead[', 'BodyTag[',
		'Unscaled[', 'WithPeak[', 'Suppress[', 'Extension[', 'Directory[',
		'Clonedirectory[', 'AbsMax[', 'Options[', 'PageTop[', 'SetEnv[',
		'Target[', 'WithPeak[', 'YTics[', 'YTicsFactor[', 'Step[',
		'PNGTitle[', 'kilo[', 'kMG[', 'XScale[', 'YScale[',
		'Colours[', 'Colour1[', 'Colour2[', 'Colour3[', 'Colour4[', 
		'ShortLegend[', 'Legend1[', 'Legend2[', 'Legend3[', 'Legend4[', 
		'Timezone[', 'RRDRowCount[', 'RRDRowCount30m[',
		'RRDRowCount2h[', 'RRDRowCount1d[', 'RRDHWRRAs[', 'TimeStrPos[',

		);

	// open mrtg config file

    if ( !file_exists( $configfile ) ) 
		return( $aconfig );

	$filedata =  file_get_contents( $configfile );
	$lines = explode( "\n", $filedata );

	$aconfig['configfile'] = $configfile;

	// manage the multi-line syntax
	for( $ln=0; $ln<count($lines); $ln++ ) {

		$line = trim( $lines[ $ln ] );
		if ( '' == $line || '#' == substr( $line, 0, 1 ) )
			continue;

		// handle multi-line... long comment or html
		$end = 0;
		while( $ln+1 < count($lines) && !$end ) {
			$fc =  substr( $lines[ $ln+1 ],0,1);
			if ( " "==$fc || "\t"==$fc ) {
				$line .= $lines[ $ln+1 ];
				$ln++;
				}
			else
				$end=1;
			}

		// double check key marker, try to avoid problem with many different user's syntax
		foreach( $asearch as $key )
			if ( strtolower( substr( $line, 0, strlen($key) ) ) == strtolower( $key ) ) {

				$lowerkey = strtolower( substr( $key,0,strlen($key)-1 ) );

				// global
				$mstring = sprintf("/%s:(.*)/i", $lowerkey );
				$ret = preg_match( $mstring, $line, $matches );
				if ( 1 == $ret && isset( $matches[1] ) )
					$aconfig[ $lowerkey ] = trim( $matches[1] );

				// with target
				$extkey = str_replace( "*","\\*", $lowerkey ); // support config extention with '*'
				$mstring = sprintf("/%s\[(.*)\]:(.*)/i", $extkey );
				$ret = preg_match( $mstring, $line, $matches );
				if ( 1 == $ret && isset( $matches[1] ) && isset( $matches[2] ) ) {
					$aconfig['targets'][ trim( $matches[1] ) ][ $lowerkey ] = trim( $matches[2] );
					// usefull later, for single config graph request
					$aconfig['targets'][ trim( $matches[1] ) ][ 'name' ] = trim( $matches[1] );
					}
				}
		}

    // define default config for each target
    if ( isset( $aconfig['targets']['_'] ) ) {
        foreach( $aconfig['targets']['_'] as $dkey => $dval ) {
            foreach( $aconfig['targets'] as $key => $val ) {
                if ( '$'==$key || '_'==$key || '^'==$key ) continue;
                if ( !isset( $aconfig['targets'][$key][$dkey] ) ) 
                    $aconfig['targets'][$key][$dkey] = $aconfig['targets']['_'][$dkey];
                }
            }
        }

	// define preprend config for each target
    if ( isset( $aconfig['targets']['^'] ) ) {
        foreach( $aconfig['targets']['^'] as $dkey => $dval ) {
            foreach( $aconfig['targets'] as $key => $val ) {
                if ( '$'==$key || '_'==$key || '^'==$key ) continue;
                if ( !isset( $aconfig['targets'][$key][$dkey] ) ) 
                    $aconfig['targets'][$key][$dkey] = $aconfig['targets']['^'][$dkey];
				else
                    $aconfig['targets'][$key][$dkey] = 
						$aconfig['targets']['^'][$dkey] . $aconfig['targets'][$key][$dkey];
                }
            }
        }

	// define append config for each target
    if ( isset( $aconfig['targets']['$'] ) ) {
        foreach( $aconfig['targets']['$'] as $dkey => $dval ) {
            foreach( $aconfig['targets'] as $key => $val ) {
                if ( '$'==$key || '_'==$key || '^'==$key ) continue;
                if ( !isset( $aconfig['targets'][$key][$dkey] ) ) 
                    $aconfig['targets'][$key][$dkey] = $aconfig['targets']['$'][$dkey];
				else
                    $aconfig['targets'][$key][$dkey] = 
						$aconfig['targets'][$key][$dkey] .  $aconfig['targets']['$'][$dkey];
                }
            }
        }


	if ( !isset( $aconfig['logformat'] ) )
		$aconfig['error'] = 'LogFormat != rrdtool';
	else
	if ( 'rrdtool' != $aconfig['logformat'] ) 
		$aconfig['error'] = 'LogFormat != rrdtool';

	// debug
    if ( -1 == getvar('debug') ) {
		echo '<PRE>'; print_r( $aconfig ); echo '<PRE>';
		die();
		}

	return( $aconfig );
}

/* build PNG from rrd file, and return the display inline generated graph */

function build_mrtg_graph( $rrdfile='', $format='daily' /*daily,weekly,monthly,yearly*/, $config )
{

	/* MRTG graph are 500x135 */
	$width = 500;
	$height = 135;

	// something goes wrong... create a blank image and add some text
    $im = imagecreatetruecolor( $width, $height );
    $text_color = imagecolorallocate($im, 255,0,0);
    imagestring($im, 5, $width/4, $height/3, ' /!\ rrd_graph faild /!\ ', $text_color);
    ob_start();
    imagejpeg($im);
    $jpg = ob_get_contents();
    ob_end_clean();
    $b64img = 'data:image/jpg;base64,';
    $b64img .= base64_encode( $jpg );
    imagedestroy($im);

	if ( !file_exists( $rrdfile ) )
		return array( 0, $b64img );

	if ( ($rrdinfo=rrd_info( $rrdfile )) === FALSE )
		return array( 0, $b64img );

	// Display RRD file info
	if ( 4 == getvar('debug') ) {
		echo '<PRE>'; echo 'rrd_version():'. rrd_version()."\n"; echo 'rrd_info():'; print_r( $rrdinfo ); echo '</PRE>';
		}

	// Display specific configuration for this target
	if ( 2 == getvar('debug') ) {
		echo '<PRE>'; print_r( $config ); echo '</PRE>';
		}

	$fileout = tempnam("/tmp", "rrdgb64");

	$title = $format;
	if ( isset( $config['title'] ) )
		$title = $config['title'] .' - '. $format. "\n";

	$ylegend = "Bits per Second";
	if ( isset( $config['ylegend'] ) )
        $ylegend = "\n". preg_replace("/[^a-zA-Z0-9]+/", "", $config['ylegend'] );

	$legendi = 'in';
	if ( isset( $config['legendi'] ) )
        $legendi = preg_replace("/[^a-zA-Z0-9]+/", "", $config['legendi'] );

	$legendo = 'out';
	if ( isset( $config['legendo'] ) )
        $legendo = preg_replace("/[^a-zA-Z0-9]+/", "", $config['legendo'] );

	if ( isset( $config['xsize'] ) )
        $width = $config['xsize'];

	if ( isset( $config['ysize'] ) )
        $height = $config['ysize'];

	if ( isset( $config['xzoom'] ) )
        $width = $width * $config['xzoom'];

	if ( isset( $config['yzoom'] ) )
        $height = $height * $config['yzoom'];

	// manuel force width || height
	if ( '' != ($w=getvar('width')) ) {
		if ( $w >= 150 && $w <= 2048 ) $width = $w;
		}
	if ( '' != ($h=getvar('height')) ) {
		if ( $h >= 50 && $h <= 2048 ) $height = $h;
		}

	$options = array();
	if ( isset( $config['options'] ) ) $options = explode( ",", $config['options'] );

	// special option to change graph type
	if ( isset( $config['rrd*graph'] ) ) {
		$options = array_merge( $options, explode( ",", $config['rrd*graph'] ) );
		}

	$now = strftime( "%Y-%m-%d %R", time() );
	$period = 0;
	$step = '';

	switch( $format ) {

		case 'debug': // you could add special case for special needs.
			break;

		case 'yearly':
			if ( !$period ) { $period = "367d"; $step='86400'; }
		case 'monthly':
			if ( !$period ) { $period = "32d"; $step='7200'; }
		case 'weekly':
			if ( !$period ) { $period = "8d"; $step='1800'; }
		case 'daily':
			if ( !$period ) { $period = 86400+3600*8; $step='300'; }

		default:

			if ( !$period ) $period = 86400+3600*8;

			if ( '' != $step )
				$step = ":step=".$step;

			/*
			To understand rrdtool_graph arguments you need to read few man:

			 % man rrdgraph
			 % man rrdgraph_data
			 % man rrdgraph_rpn
			 % man rrdgraph_graph

			After reading... may be it's clear as mud... in this case re-RTFM \o/
			*/

			$bformat = array(
                "--imgformat", "PNG",
                "--width=".$width,
                "--height=".$height,
                "-v", $ylegend,
                "-t", $title,
                "--use-nan-for-all-missing-data",
                "--start", "-".$period,
                "--end", sprintf("%d",time()-300),
                "--watermark", "generated by michot-tools ".$now, // troll from friends
				);

			if ( in_array( 'autoscale', $options ) )
				array_push( $bformat, "--alt-autoscale" );
			else
			if ( in_array( 'autoscale-max', $options ) )
				array_push( $bformat, "--alt-autoscale-max" );
			else
			if ( in_array( 'autoscale-min', $options ) )
				array_push( $bformat, "--alt-autoscale-min" );

			if ( in_array( 'forceday', $options ) && ('monthly' == $format || 'weekly' == $format) ) {
				array_push( $bformat, "--week-fmt" );
				array_push( $bformat, ' %d %b' );
				}

			if ( getvar('factor') != 0 ) 
				$config['factor'] = getvar('factor');

			if ( isset( $config['factor'] ) ) {
				if ( $config['factor'] > 0.1 &&  $config['factor'] <= 4 ) {
					array_push( $bformat, "--zoom" );
					array_push( $bformat, $config['factor'] );
					}
				}

			$multi='1';
			if ( in_array( 'bits', $options ) ) $multi='8';

			if ( in_array( 'noo', $options ) ) {
				$b1format = array (
	                "DEF:davg0=". $rrdfile .":ds0:AVERAGE".$step,
					"DEF:dmin0=". $rrdfile .":ds0:MIN".$step,
					"DEF:dmax0=". $rrdfile .":ds0:MAX".$step,
	                "VDEF:ds0max=davg0,MAXIMUM",
	                "VDEF:ds0avg=davg0,AVERAGE",
	                "VDEF:ds0min=davg0,MINIMUM",
	                "VDEF:ds0last=davg0,LAST",
					);
				$d=0;
				}
			else
			if ( in_array( 'noi', $options ) ) {
				$b1format = array (
	                "DEF:davg1=". $rrdfile .":ds1:AVERAGE".$step,
					"DEF:dmin1=". $rrdfile .":ds1:MIN".$step,
					"DEF:dmax1=". $rrdfile .":ds1:MAX".$step,
	                "VDEF:ds1max=davg1,MAXIMUM",
	                "VDEF:ds1avg=davg1,AVERAGE",
	                "VDEF:ds1min=davg1,MINIMUM",
	                "VDEF:ds1last=davg1,LAST"
					);
				$d=1;
				}
			else {
				$b1format = array (
					"DEF:davg0=". $rrdfile .":ds0:AVERAGE".$step,
					"DEF:davg1=". $rrdfile .":ds1:AVERAGE".$step,
					"DEF:dmin0=". $rrdfile .":ds0:MIN".$step,
					"DEF:dmin1=". $rrdfile .":ds1:MIN".$step,
					"DEF:dmax0=". $rrdfile .":ds0:MAX".$step,
					"DEF:dmax1=". $rrdfile .":ds1:MAX".$step,
					"VDEF:ds0max=davg0,MAXIMUM",
					"VDEF:ds0avg=davg0,AVERAGE",
					"VDEF:ds0min=davg0,MINIMUM",
					"VDEF:ds0last=davg0,LAST",
					"VDEF:ds1max=davg1,MAXIMUM",
					"VDEF:ds1avg=davg1,AVERAGE",
					"VDEF:ds1min=davg1,MINIMUM",
					"VDEF:ds1last=davg1,LAST",
					);
				$d=2;
				}


			if ( 0 == $d ) {
				array_push( $b1format, "CDEF:ds0bits=davg0,".$multi.",*" );
				array_push( $b1format, "CDEF:ds0maxbits=dmax0,".$multi.",*" );
				array_push( $b1format, "CDEF:ds0avgbits=davg0,".$multi.",*" );
				array_push( $b1format, "CDEF:ds0minbits=dmin0,".$multi.",*" );
				}
			else
			if ( 1 == $d ) {
				array_push( $b1format, "CDEF:ds1bits=davg1,".$multi.",*" );
				array_push( $b1format, "CDEF:ds1maxbits=dmax1,".$multi.",*" );
				array_push( $b1format, "CDEF:ds1avgbits=davg1,".$multi.",*" );
				array_push( $b1format, "CDEF:ds1minbits=dmin1,".$multi.",*" );
				}
			else  {
				array_push( $b1format, "CDEF:ds0bits=davg0,".$multi.",*" );

				if ( in_array( '2waygraph', $options ) )
					array_push( $b1format, "CDEF:ds1bits=davg1,".'-'.$multi.",*" );
				else
					array_push( $b1format, "CDEF:ds1bits=davg1,".$multi.",*" );

				array_push( $b1format, "CDEF:ds0maxbits=dmax0,".$multi.",*" );
				array_push( $b1format, "CDEF:ds0avgbits=davg0,".$multi.",*" );
				array_push( $b1format, "CDEF:ds0minbits=dmin0,".$multi.",*" );
				array_push( $b1format, "CDEF:ds1maxbits=dmax1,".$multi.",*" );
				array_push( $b1format, "CDEF:ds1avgbits=davg1,".$multi.",*" );
				array_push( $b1format, "CDEF:ds1minbits=dmin1,".$multi.",*" );

				}

			$ds0p = $ds1p = array();

			$gmode0 = 'AREA';
			$gmode1 = 'LINE';
			if ( in_array( '2waygraph', $options ) ) 
				$gmode0 = $gmode1 = 'AREA';
			if ( in_array( 'forceline', $options ) ) 
				$gmode0 = $gmode1 = 'LINE';
			if ( in_array( 'forcearea', $options ) ) 
				$gmode0 = $gmode1 = 'AREA';

			$ds0p = array(
				$gmode0.":ds0bits#00C000:".sprintf("%-11.11s", $legendi ),
				"GPRINT:ds0maxbits:MAX:%8.2lf %S   ",
				"GPRINT:ds0avgbits:AVERAGE:%8.2lf %S   ",
				"GPRINT:ds0minbits:MIN:%8.2lf %S   ",
				"GPRINT:ds0avgbits:LAST:%8.2lf %S   \l",
				);
			$ds1p = array(
				$gmode1.":ds1bits#0000FF:".sprintf("%-11.11s", $legendo ),
				"GPRINT:ds1maxbits:MAX:%8.2lf %S   ",
				"GPRINT:ds1avgbits:AVERAGE:%8.2lf %S   ",
				"GPRINT:ds1minbits:MIN:%8.2lf %S   ",
				"GPRINT:ds1avgbits:LAST:%8.2lf %S   \l",
				);

			if ( 0 == $d )
				$dsp = $ds0p;
			else
			if ( 1 == $d )
				$dsp = $ds1p;
			else
				$dsp = array_merge( $ds0p, $ds1p );

			$aformat = array_merge( $bformat, $b1format,
				array(
					"COMMENT:                ",
					"COMMENT:Maximum      ", 
					"COMMENT:Average      ",
					"COMMENT:Minimum      ",
					"COMMENT:Current     \l" ),
				$dsp
			 	);

			if ( 5 == getvar('debug') ) {
				echo '<PRE>build aformat result:'; print_r( $aformat ); echo '</PRE>';
				}

		break;
		}

	$ra = rrd_graph( $fileout, $aformat );

	if ( 3 == getvar('debug') ) {
        echo '<PRE>';
        echo '% rrdtool graphv '.$fileout.' ';

        foreach( $aformat as $val )
            printf( "\"%s\" \\\n", trim( $val ) );

        echo "\nreturn: ";
		print_r( $ra );
        echo "\nrrd_fetch():\n";
                //array( "AVERAGE", "--resolution", "60", "--start", "-1d", "--end", "start+1h" ) );
        $result = rrd_fetch( $rrdfile, 
                array( "AVERAGE", "--resolution", "60", "--start", '-'.$period , "--end", sprintf("%d",time()-300)  )
				);
        print_r( $result );
        echo '</PRE>';
		}

    if ( file_exists( $fileout ) ) {
        $size = filesize( $fileout );
		$img =  file_get_contents( $fileout );
		unlink( $fileout );

        if ( $size < 1024 ) // rrdgraph unable to generate img ?
		    return( array( 0, $b64img ) );

		// ugly hack to get real PNG file. Image may be include somewhere in html
		if ( in_array( getvar('png'), array('daily','weekly','monthly','yearly') ) &&
			$format == getvar('png') ) {

		    header("Content-Type: image/png");
			echo $img;
			die();
			}

        ob_start();
		echo $img;
        $jpg = ob_get_contents();
        ob_end_clean();
        $b64img = 'data:image/png;base64,';
        $b64img .= base64_encode( $jpg );

		$lu = 1;
		if ( isset( $rrdinfo['last_update'] ) )
			$lu =  $rrdinfo['last_update'];

		return( array( $lu, $b64img ) );
		}

    return( array( 0, $b64img ) );

}

function getvar( $var )
{
    if ( isset( $_GET[$var]) )
		return( $_GET[$var] );
	return( '' );
}


	// main 
	
	// check if we have all we need to run.
	$weneed = get_loaded_extensions();
	if ( !in_array( 'gd', $weneed ) ) {
		echo "Missing <a href='https://www.php.net/manual/en/book.image.php'>GD extention</a><P>\n";
		die();
		}
	if ( !in_array( 'rrd', $weneed ) ) {
		echo "Missing <a href='https://www.php.net/manual/en/book.rrd.php'>RRD extention</a><P>\n";
		die();
		}

	$cfg = getvar( 'cfg' );
	$target = getvar( 'target' );
	$png = getvar( 'png' );

	// run in CGI or module ?
	$uri =  isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : $_SERVER['SCRIPT_NAME'];

	$html = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">';
	$html .= '<HTML>';

	// list all config available
    $menu = '';
    $dsplen = 0;
	foreach( $mrtgconfigfiles as $cf ) {
		$configfile =  substr( strrchr( $cf,'/'),1 );
        $dsplen += 4 + strlen($configfile);
        if ( $dsplen > 120 ) {
            $menu .= "<br>\n";
            $dsplen = 0;
            }
        else
    		if ( strlen($menu) ) $menu .= " , ";
		$menu .=  '<a href="'. $uri .'?cfg='. $configfile .'">'.$configfile.'</a>';
		}
	$menu .= ' , <a href="'. $uri .'?cfg=all">all</a>';
	$html = '<a href="'.$uri.'">&gt;</a>'."\n". $menu;

	if ( '' == $cfg ) {
		$html .= '</HTML>';
		echo $html;
		die();
		}

	// request full page for only one target
	if ( $cfg != '' && $target != '' ) {

		foreach( $mrtgconfigfiles as $cf ) {
			if ( $cfg == substr( strrchr( $cf,'/'), 1 ) ) {
				$config = read_mrtg_config_file( $cf );

				$rrdfile = $config['workdir'].'/'.strtolower($target).'.rrd';
				$rrdinfo = rrd_info( $rrdfile );

				if ( isset( $config['refresh'] ) )
					header("Refresh:".$config['refresh']);

				// real html or just one png ?
				if ( '' == $png ) {
					echo $html;

					if ( isset( $config[ 'targets' ][ $target ]['pagetop'] ) )
						printf("%s", $config[ 'targets' ][ $target ]['pagetop'] );

					if ( isset( $rrdinfo['last_update'] ) ) 
						echo '<br>The statistics were last updated Saturday, '. strftime("%c",$rrdinfo['last_update'] );

					if ( isset( $config[ 'targets' ][ $target ]['rrd*htmlhead'] ) ) {
    					if ( file_exists( $config[ 'targets' ][ $target ]['rrd*htmlhead'] ) ) 
							echo file_get_contents( $config[ 'targets' ][ $target ]['rrd*htmlhead'] );
						}


					if ( getvar('debug')==99 ) {
						echo '<PRE>'; echo 'rrd_version():'. rrd_version()."\n"; 
						echo 'rrd_info():'; print_r( $rrdinfo ); echo '</PRE>';
						}
					}

				$aperiod = array( 'daily'=>"5 Minutes",'weekly'=>"30 Minutes",'monthly'=>"2 Hours",'yearly'=>"1 Day" );

				foreach( $aperiod as $period => $avg ) {

					if ( '' == $png ) 
						echo "<h1>'".ucfirst($period)."' Graph (".$avg." Average)</h1>\n";

					list( $r, $img ) = build_mrtg_graph( $rrdfile, $period, $config[ 'targets' ][ $target ] );

					// link for real PNG file and not inline PNG data
					if ( '' == $png ) 
						printf("<a href='%s?cfg=%s&target=%s&png=%s'><img src='%s'></a><P>",
							$uri, substr( strrchr( $config['configfile'],'/'), 1 ), 
							$config['targets'][ $target ]['name'], $period, $img );
					}
				}
			}
		
		if ( '' == $png ) 
			echo '</HTML>';
		die();
		}

	if ( 'all' == $cfg ) // you realy want all your config on one big web page.
		$cfg = '';

	// process all config file (or just one)
	$i = 0;
	foreach( $mrtgconfigfiles as $cf )
		$aconfig[ $i++ ] = read_mrtg_config_file( $cf );

	// each config file may have it's own refresh, use default 300
	header("Refresh:300");

	echo $html;

	foreach( $aconfig as $config ) {

		if ( $cfg != '' && $cfg != substr( strrchr( $config['configfile'],'/'), 1 ) )
			continue;

		printf("<hr><h1>ConfigFile: %s</h1>\n", substr( strrchr($config['configfile'],'/'),1 ) );

		// something wrong with the config file ?
		if ( isset( $config['error'] ) ) {
			printf("<br>#Error: %s File: %s\n", $config['error'], $config['configfile'] );
			continue;
			}

		if ( !isset( $config['targets'] ) )
			continue;

		// humm... keep old style html generated by MRTG indexmaker instead of CSS style
		echo "<TABLE BORDER=0 CELLPADDING=0 CELLSPACING=10>";

		foreach( $config['targets'] as $key => $adata ) {

			$rrdfile = $config['workdir'].'/'.strtolower($key).'.rrd';

			if ( '_' == $key || '$' == $key ) continue;

			if ( !file_exists( $rrdfile ) ) {
				printf("#Error: missing file %s<br>\n", $rrdfile );
				continue;
				}

			$href =  sprintf("'%s?cfg=%s&target=%s'", $uri, substr( strrchr( $config['configfile'],'/'), 1 ), $key );

			$row = "<tr>";
			$htmltarget = "<td><DIV>";
			if ( isset( $config['targets'][ $key ] ['title'] ) )
				$htmltarget .= sprintf("<B><a href=%s>%s</a></B>", $href,  $config[ 'targets' ][ $key ]['title'] );
			$htmltarget .= "</DIV>\n";
			$row .= $htmltarget;

			// display daily for main page of config file
			list( $r, $img ) = build_mrtg_graph( $rrdfile, 'daily', $config[ 'targets' ][ $key ] );

			$row .= "<DIV>";	
			$row .= sprintf("\n<a href=%s><img border=1 src='%s'></a>\n", $href, $img );
			$row .= "</DIV></td>\n";

			// display daily and weekly on config page
			if ( $display_main_dw ) {
				list( $r, $img ) = build_mrtg_graph( $rrdfile, 'weekly', $config[ 'targets' ][ $key ] );

				$row .= $htmltarget;
				$row .= "<DIV>";	
				$row .= sprintf("\n<a href=%s><img border=1 src='%s'></a>\n", $href, $img );
				$row .= "</DIV></td>\n";

				}
			$row .= "</tr>\n";
			echo $row;
			}
		echo "</TABLE><P>";
	}

	echo '</HTML>';

?>
