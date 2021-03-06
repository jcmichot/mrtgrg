<h1>MRTG RRD GRAPH</h1>
<p align="right">
  <img src="mrtgrg-sensor.png" width="350" title="sensor example">
</p>

The purpose of this small program is to generate html pages on the fly
from MRTG rrd files. 

html and images are built in realtime, this mean no .html or .png file
stored on server. <br>
For a fast display with many small image, the html 
include inline png data.

This program is a rainy saturday project, but it may be useful to some 
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

[url]/mrtgrg.php<br>
[url]/mrtgrg.php?cfg=mrtg.cfg&target=port24<br>
[url]/mrtgrg.php?cfg=mrtg.cfg&target=port24&png=weekly<br>

 To manualy force width &| height 
 (this can also be configured inside mrtg config file):
 
[url]/mrtgrg.php?cfg=homebridge.cfg&target=power-home&png=weekly&width=1400&height=300
 
 To manualy force 2waygraph:

[url]/mrtgrg.php?cfg=homebridge.cfg&target=power-home&png=weekly&width=1280&height=450&force2way=1

Define start and stop argument variable to force graph a specific period inside last 30 days:
[url]/mrtgrg.php?cfg=homebridge.cfg&target=tempext-home&png=daily&start=202204092000&end=202204101200
(start to 2022-04-09 20:00, end to 2022-04-10 12:00)

A new option is available in your MRTG config file:

    Rrd*Graph[ TARGET ]: 2waygraph,forcearea,forceday

    forceline: force display line/s
    forcearea: force display area/s
    forceday: do not use week number in month graph
    2waygraph: graph in/ou in 2 way (up/down a 0 line) (enable forcearea)
    autoscale: man rrdgraph
    autoscale-max: man rrdgraph
    autoscale-min: man rrdgraph
    
All test has been done with:
 FreeBSD and pkg mod_php74-7.4.27 php74-gd-7.4.23 php74-pecl-rrd-2.0.1_1

Example of 2waygraph with 95 percentil:
<p align="center">
  <img src="2waygraph.png" width="850" title="network interface example">
</p>

Example of network interface page:
<p align="center">
  <img src="mrtgrg-inout-ge.png" width="850" title="network interface example">
</p>

