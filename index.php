<?php
$client_ip = $_SERVER['REMOTE_ADDR'];
$browser = strpos($_SERVER['HTTP_USER_AGENT'],"iPhone");
    if ($browser == true){
    $browser = 'iphone';
  }
?>
<!DOCTYPE html>
<html>
<head>
    <title>CMS Vis</title>
    <!-- D3 and other req. libraries -->
    <script src="http://d3js.org/d3.v3.min.js">
    </script>
    <script src="http://d3js.org/topojson.v1.min.js">
    </script>
    <script src="http://d3js.org/queue.v1.min.js">
    </script>
    <script src='http://cdn.ractivejs.org/latest/ractive.js'>
    </script>
    <script src="https://code.jquery.com/jquery-3.1.1.min.js">
    </script>
    <script src="https://use.fontawesome.com/bb47d90cfb.js"></script>
    <script src="http://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU="crossorigin="anonymous"></script>
    <!-- fonts -->
    <link href="https://fonts.googleapis.com/css?family=Kameron" rel="stylesheet">
    <link href="vis-style.css" rel="stylesheet" type="text/css">
    <meta charset="utf-8">
</head>

<body>
<div id = "container">
    <div id="graphic-container">
    	<!-- injection point for drop down -->
    	<div id="menu-container">
    	<div id = "dropdown-title">Map color based on:</div>
    	<select id = "dropdown">
			<option value="number_cms_entries" selected = "selected">number_cms_entries</option>
			<option value="total_med_submitted_chrg_amt"> total_med_submitted_chrg_amt</option> 
			<option value="total_med_medicare_allowed_amt">total_med_medicare_allowed_amt</option>
			<option value="total_med_medicare_payment_amt">total_med_medicare_payment_amt</option> 
			<option value="population">population</option> 
  		</select>
  		<input class = "filter-button" id = "per-capita" type="checkbox">per capita</input>  
    	</div>
    	<i class="fa fa-info" id="about" aria-hidden="true"></i>
    	<br>
        <!--injection point for reference color bar -->
        <div id="reference-bar"></div>
        <br>
        <!-- injection point for D3 generated map -->
        <div id="map"></div>
        
        <!-- injection for ractive generated information on mouseover -->
        <?php if($browser == 'iphone'){
        		echo "<br><div id = \"phone-info-container\">";
        		}
        	  else{
        	  	echo "<div id = \"info-container\">";
        	  } 
        ?>
    	<div id="info"></div>
    	<br>
    	<div id = "state-selector">
    		<select id="states-dropdown">
    		</select>
    	</div>
    	</div>
    </div>
    <div id="client-info-container">
        	<p>Connected from IP: <?php echo $client_ip;?></p>
    	</div>
</div>
<!-- globals -->
    <script>
            var global_colorspace = d3.scale.linear()
                .range(["hsl(62,100%,90%)", "hsl(222,30%,20%)"]);
            var global_percapita_suffix = "_percapita";
    </script> <!-- code to generate reference-bar -->
     
    <script>
            var space = [{
                name: "HCL",
                interpolate: d3.interpolateHcl
            }];

            var y = d3.scale.ordinal()
                .domain(space.map(function(d) {
                    return d.name;
                }))
                .rangeRoundBands([0, 20], 0.09);

            var absoluteWidth = 600,
                margin = y.range()[0],
                width = absoluteWidth - margin * 2,
                height = y.rangeBand();

            var color = global_colorspace.domain([0, width]);

            var space = d3.select("#reference-bar").selectAll(".space")
                .data(space)
                .enter()
                .append("div")
                .attr("class", "space")
                .style("width", width + "px")
                .style("height", height + "px")
                .style("left", margin + "px")
                .style("top", function(d, i) {
                    return y(d.name) + "px";
                });
           d3.select("#menu-container")
             .style("width", width-margin*10+"px");

            space.append("canvas")
                .attr("width", width)
                .attr("height", 1)
                .style("width", width + "px")
                .style("height", height + "px")
                .each(render);
            space.append("div")
            	.attr("id", "reference-title")
                .style("line-height", height + "px")
                .style("width", width+"px");
                

            function render(d) {
                var context = this.getContext("2d");
                image = context.createImageData(width, 1);

                color.interpolate(d.interpolate);
                for (var i = 0, j = -1, c; i < width; ++i) {
                    c = d3.rgb(color(i));
                    image.data[++j] = c.r;
                    image.data[++j] = c.g;
                    image.data[++j] = c.b;
                    image.data[++j] = 255;
                }
                context.putImageData(image, 0, 0);
            }
    </script>     
    <!-- js code to generate map and control interactions -->
     
    <script>
            var width = 870,
                height= 500;

            var path = d3.geo.path();
                     
            var svg = d3.select("#map").append("svg")
                .attr("width", width)
                .attr("height", height);
           
           function fix(x){
           		var xfloat = parseFloat(x);
           		return xfloat.toFixed(5);
           }
            function formattedNumber(x) {
            	var num = Math.round(x);
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            //populations of all states
            var cms_data, //json loaded dataset
                population_stats, //min,max of population
                populations; // state:population mapping
            
            
            function getPopulations(){
            	populations = {}
            	d3.select("#map").selectAll("path").each(function(d,i){
            		var state = d3.select(this).attr("name"),
            			population = d3.select(this).attr("population");
            		if (state == "null"){
            			return;
            		}
            		populations[state] = population;
            	});
            	var arr = Object.keys( populations ).map(function ( key ) { return parseInt(populations[key]); });         
            	var min = Math.min.apply(Math, arr),
            		max = Math.max.apply(null, arr);
        	
            	population_stats = {
            		"min":min,
            		"max":max
            	};
            }
            
            function minMax(json, axis, perCapita){
                var min = Number.MAX_VALUE,
                    max = Number.MIN_VALUE;
                for (var key in json){
                    var normalization_factor;
                    if (perCapita){
                    	normalization_factor = populations[key];
                    }
                    else{
                    	normalization_factor = 1;
                    }
                    var axis_value = json[key][axis]/normalization_factor;
                    //min
                    if (axis_value < min){
                        min = axis_value;
                    }
                    //max
                    if(axis_value > max){
                        max = axis_value;
                    }
                }
                var obj = {
                    "min" : min,
                    "max" : max
                };
                return obj;
            }
            
            var absoluteCmsValues,
            	normalizedCmsValues;
            	
            function getMinMax(){
            
            	absoluteCmsValues = {
            		"percapita"						 : false,
                	"total_med_submitted_chrg_amt"   : minMax(cms_data, "total_med_submitted_chrg_amt", false),
                	"total_med_medicare_allowed_amt" : minMax(cms_data, "total_med_medicare_allowed_amt", false),
                	"total_med_medicare_payment_amt" : minMax(cms_data, "total_med_medicare_payment_amt", false),
                	"number_cms_entries"             : minMax(cms_data, "number_cms_entries",false),
                	"population"                     : population_stats
                	};
                normalizedCmsValues = {
                	"percapita"						 : true,
                	"total_med_submitted_chrg_amt"   : minMax(cms_data, "total_med_submitted_chrg_amt", true),
                	"total_med_medicare_allowed_amt" : minMax(cms_data, "total_med_medicare_allowed_amt", true),
                	"total_med_medicare_payment_amt" : minMax(cms_data, "total_med_medicare_payment_amt", true),
                	"number_cms_entries"             : minMax(cms_data, "number_cms_entries",true),
                	"population"                     : {"min": 1, "max":1}
                	};
            }
            
            //return colorspace mapped to min and max values f stats_obj
            function getColorspace(stats_obj, colorspace){
                var min = stats_obj["min"],
                    max = stats_obj["max"];
                return colorspace.domain([min, max]);
            }
            
            function updateStates(axis, container){
            	var percapita = container["percapita"];
                var stats_obj,
                	dollar;
                switch (axis){
                    case "total_med_submitted_chrg_amt":
                        stats_obj = container[axis];
                        dollar=true;
                        break;
                    case "total_med_medicare_allowed_amt":
                        stats_obj = container[axis];
                        dollar=true;
                        break;
                    case "total_med_medicare_payment_amt":
                        stats_obj = container[axis];
                        dollar=true;
                        break;
                    case "number_cms_entries":
                        stats_obj = container[axis];
                        break;
                    case "population":
                    	stats_obj = container[axis];
                    	break;
                    default:
                        stats_obj = {"min":0, "max":1};
                        dollar=false;
                }
                var updateMin = stats_obj["min"],
                	updateMax = stats_obj["max"];
                if (percapita && axis == "number_cms_entries"){
                	updateMin = fix(updateMin);
                	updateMax = fix(updateMax);
                }
                else{
                	updateMin = formattedNumber(updateMin);
                	updateMax = formattedNumber(updateMax);
                }
                if (dollar){
                	updateMin = "$"+updateMin;
                	updateMax = "$"+updateMax;
                }
                ractive_reference.set("min",updateMin);
                ractive_reference.set("max",updateMax);
                var colorspace = getColorspace(stats_obj, global_colorspace);
                if (percapita){
                	axis = axis + global_percapita_suffix;
                }
                //color
                svg.selectAll("path").each(function(d,i){
                    var val = d3.select(this).attr(axis);
                    var color = colorspace(val);
                    d3.select(this).attr("color", color)
                                    .style("fill", color);
                });
            }

            $.when(
                $.getJSON("json_data.json"))
                .done(function(json){
                    cms_data = json;
                    jsonLoadCallback();
               		constructStateSelection();
                });
                
           function constructStateSelection(){
           		for (var key in cms_data){
           			var options_html = "<option value=\""+key+"\">"+key+"</option>";
           			$("#states-dropdown").append(options_html);
           		}
           }
           
           function updateInfo(name) {
			    var percapita = d3.select("#per-capita").property("checked");
			    var suffix = "";
			    if (percapita) {
			        suffix = global_percapita_suffix;
			    }
			    var population = d3.select("[name=\"" + name + "\"]").attr("population"),
			        total_med_submitted_chrg_amt = d3.select("[name=\"" + name + "\"]").attr("total_med_submitted_chrg_amt" + suffix),
			        total_med_medicare_allowed_amt = d3.select("[name=\"" + name + "\"]").attr("total_med_medicare_allowed_amt" + suffix),
			        total_med_medicare_payment_amt = d3.select("[name=\"" + name + "\"]").attr("total_med_medicare_payment_amt" + suffix),
			        number_cms_entries = d3.select("[name=\"" + name + "\"]").attr("number_cms_entries" + suffix);
			    ractive.set("state", name);
			    ractive.set("population", formattedNumber(population));
			    if (!percapita) {
			        ractive.set("number_cms_entries", formattedNumber(number_cms_entries));
			    } else {
			        ractive.set("number_cms_entries", fix(number_cms_entries));
			    }
			    ractive.set("total_med_submitted_chrg_amt", formattedNumber(total_med_submitted_chrg_amt));
			    ractive.set("total_med_medicare_payment_amt", formattedNumber(total_med_medicare_payment_amt));
			    ractive.set("total_med_medicare_allowed_amt", formattedNumber(total_med_medicare_allowed_amt));
			}
			
			function updateStatesDropdown(name){
				d3.select("#states-dropdown").property("value", name);
			}
            
            //called when json_data is loaded
            function jsonLoadCallback(){
                queue()
                    .defer(d3.json, "us.json")
                    .defer(d3.json, "us-state-centroids.json")
                    .await(ready);
                }

            function ready(error, us, centroid) {
            	
                var countries = topojson.feature(us, us.objects.states).features,
                    neighbors = topojson.neighbors(us.objects.states.geometries);

                svg.selectAll("states")
                    .data(countries)
                    .enter()
                    .insert("path", ".graticule")
                    .attr("class", "states")
                    .attr("name", function(d, i) {
                        if (i <= 51) {
                            return centroid["features"][i]["properties"]["name"];
                        } else {
                            return "null";
                        }
                    })
                    .attr("population", function(d, i) {
                        if (i <= 51) {
                            return centroid["features"][i]["properties"]["population"];
                        } else {
                            return "null";
                        }
                    })
                    .attr("population_percapita", 1)
                    .attr("total_med_submitted_chrg_amt",function(d,i){
                        if (i <= 51){
                            var name = d3.select(this).attr("name");
                            return cms_data[name]["total_med_submitted_chrg_amt"];
                        }
                        else{
                            return "null";
                        }
                    })
                    .attr("total_med_submitted_chrg_amt_percapita",function(d,i){
                        if (i <= 51){
                            var name = d3.select(this).attr("name");
                            var population = d3.select(this).attr("population");
                            return cms_data[name]["total_med_submitted_chrg_amt"]/population;
                        }
                        else{
                            return "null";
                        }
                    })
                    .attr("total_med_medicare_allowed_amt",function(d,i){
                        if (i <=51){
                        var name = d3.select(this).attr("name");
                        return cms_data[name]["total_med_medicare_allowed_amt"];
                        }
                        else{
                        return "null";
                        }
                    })
                    .attr("total_med_medicare_allowed_amt_percapita",function(d,i){
                        if (i <=51){
                        var name = d3.select(this).attr("name");  
                        var population = d3.select(this).attr("population");
                        return cms_data[name]["total_med_medicare_allowed_amt"]/population;
                        }
                        else{
                        return "null";
                        }
                    })
                    .attr("total_med_medicare_payment_amt",function(d,i){
                    if (i <= 51){
                        var name = d3.select(this).attr("name");
                        return cms_data[name]["total_med_medicare_payment_amt"];
                        }
                        else{
                            return "null";
                        }
                    })
                    .attr("total_med_medicare_payment_amt_percapita",function(d,i){
                    if (i <= 51){
                        var name = d3.select(this).attr("name");
                        var population = d3.select(this).attr("population");
                        return cms_data[name]["total_med_medicare_payment_amt"]/population;
                        }
                        else{
                            return "null";
                        }
                    })
                    .attr("state_two_letter_code",function(d,i){
                    if (i <=51){
                        var name = d3.select(this).attr("name");
                        var population = d3.select(this).attr("population");
                        return cms_data[name]["state_two_letter_code"]/population;
                        }
                        else{
                            return "null";
                        }
                    })
                    .attr("number_cms_entries",function(d,i){
                    if (i <= 51){
                        var name = d3.select(this).attr("name");
                        return cms_data[name]["number_cms_entries"];
                        }
                        else{
                        return "null";
                        }
                    })
                    .attr("number_cms_entries_percapita",function(d,i){
                    if (i <= 51){
                        var name = d3.select(this).attr("name");
                        var population = d3.select(this).attr("population");
                        return cms_data[name]["number_cms_entries"]/population;
                        }
                        else{
                        return "null";
                        }
                    })
                    .attr("d", path)
                    .style("fill", function(d, i) {
                        var newColor = color(i);
                        return newColor;
                    })
                    .on('mouseover', function(d, i) {                    	
                        d3.select(this).style('fill-opacity', 1);
                        d3.select(this).style('fill', '#fff');
                        d3.select(this).style('stroke', '#000');
                        var name = d3.select(this).attr("name");
                        updateInfo(name);
                        updateStatesDropdown(name);
                    })
                    .on('mouseout', function(d, i) {
                        d3.selectAll('path')
                            .style({
                                'fill-opacity': .7
                            });
                        d3.select(this).style('fill', d3.select(this).attr('color'));
                        d3.select(this).style('stroke', '#fff');
                    });
                    getPopulations();
                    getMinMax();
                    updateStates("number_cms_entries", absoluteCmsValues);
                    updateInfo("Texas");
                    updateStatesDropdown("Texas");
            }
    </script>
    <!-- updates based on selectmenu option choice -->
    <script>
	d3.select("#dropdown")
	  .on("change", function(){
	  	var val = d3.select(this).property("value");
	  	var perCapita = d3.select("#per-capita").property("checked");
	  	
	  	if (perCapita){
	  		updateStates(val, normalizedCmsValues);
	  	}
	  	else{
	  		updateStates(val, absoluteCmsValues);
	  	}
	  });
    
    </script>
        <!-- script to control information area. Ractive should go last. -->
    <script id="template" type='text/ractive'>
    <div class="table-title">
    	<h3>Selection Information<br>(CMS Aggregate Y2014)</h3>
    </div>
    <table class="table-fill">
    <thead>
    <tr>
    <th class="text-left">CMS Entry Name</th>
    <th class="text-left">Value</th>
    </tr>
    </thead>
    <tbody class="table-hover">
    <tr>
    <td class="text-left">State</td>
    <td class="text-left val">{{state}}</td>
    </tr>
    <tr>
    <td class="text-left">Population</td>
    <td class="text-left val">{{population}}</td>
    </tr>
    <tr>
    <td class="text-left">Number CMS Entities</td>
    <td class="text-left val">{{number_cms_entries}}</td>
    </tr>
    <tr>
    <td class="text-left">Total Medical Medicare Submitted Charges</td>
    <td class="text-left val">${{total_med_submitted_chrg_amt}}</td>
    </tr>
    <tr>
    <td class="text-left">Total Medical Medicare Allowed Payments</td>
    <td class="text-left val">${{total_med_medicare_allowed_amt}}</td>
    </tr>
    <tr>
    <td class="text-left">Total Medical Medicare Payments</td>
    <td class="text-left val">${{total_med_medicare_payment_amt}}</td>
    </tr>
    </tbody>
    </table>
    </script> 
    <script id = "reference-template" type = 'text/ractive'>
    	<p style="float:left; padding-left: 40px">Min = {{min}}</p>
    	<p style="float:right; padding-right:40px">Max = {{max}}</p>
    </script>
    <script>
            var ractive = new Ractive({
                el: "#info",
                template: '#template',
                data: {}
            });
            
            var ractive_reference = new Ractive({
            	el:"#reference-title",
            	template:'#reference-template',
            	data:{}
            });
    </script>
    <!-- per capita checkbox -->
    <script>
    	d3.select("#per-capita").on("change",function(){
    		var checked = this.checked;
    		var selection = d3.select("#dropdown").property("value");
    		var current_state = d3.select("#states-dropdown").property("value");
    		updateInfo(current_state);
    		if (checked){
    			updateStates(selection, normalizedCmsValues);
    		}
    		else{
    			updateStates(selection, absoluteCmsValues);
    		}
    	});
    </script>
    
    <script>
    	d3.select("#states-dropdown").on("change",function(){
    		var name = d3.select("#states-dropdown").property("value");
    		updateInfo(name);
    		var selection = d3.select("[name=\"" + name + "\"]");
    		selection.transition()
    		  		 .style('fill-opacity', 1)
              		 .style('fill', '#fff')
             		 .transition()
             		 .delay(1000)
             		 .style('fill-opacity', 0.7)
             		 .style('fill', selection.attr('color'));
    	})
    </script>
</body>
</html>