function clearBoxes()
{
    var index;
    divs=["tablesearch","table","column","treemap","all_search_name","all_search_fileset"];
    for (index = 0; index < divs.length; ++index) {
        var el = document.getElementById(divs[index]);
        if (el) el.innerHTML = "";
    }
}

google.load('visualization', '1', {'packages':['table','controls']});
google.load("visualization", '1', {'packages':['corechart']});
google.load("visualization", '1', {'packages':['treemap']});
google.setOnLoadCallback();

    /**
     * Draws the user table with filter controls.
     * Uses AJAX to fetch JSON data from the server and renders a Google Table.
     * Security: Avoids direct user input in query string, but relies on server-side sanitization.
     */
    function drawTableUsers(cluster, str) {
      // Fetch user data for a cluster/fileset
      var jsonData = $.ajax({
          url: "users_table.php?cluster=" + encodeURIComponent(cluster) + "&fileset=" + encodeURIComponent(str),
          dataType: "json",
          async: false
          }).responseText;
      console.log("users_table.php JSON:", jsonData); // Debug print
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);

      // Add filter control for user column
      var stringFilter = new google.visualization.ControlWrapper({
          'controlType': 'StringFilter',
          'containerId': 'tablesearch',
          'options': {
            'filterColumnLabel': 'User',
            'matchType': 'any',
            'ui': { 'cssClass': 'google-visualization-table-table'}
            }
      });

      // Table visualization
      var table = new google.visualization.ChartWrapper({
          chartType: 'Table',
          containerId: 'table',
          options: {
            showRowNumber: true,
            allowHtml: true
          }
      });

      // Bind filter to table and render
      var dashboard = new google.visualization.Dashboard(document.querySelector('#dashboard'));
      dashboard.bind([stringFilter], [table]);
      dashboard.draw(data);
    }

    /**
     * Draws the experiment table with filter controls.
     * Uses AJAX to fetch JSON data from the server and renders a Google Table.
     * Security: Avoids direct user input in query string, but relies on server-side sanitization.
     */
    function drawTableExperiments(cluster, str) {
      // Fetch experiment data for a cluster/fileset
      var jsonData = $.ajax({
          url: "experiments_table.php?cluster=" + encodeURIComponent(cluster) + "&fileset=" + encodeURIComponent(str),
          dataType: "json",
          async: false
          }).responseText;
      console.log("experiments_table.php JSON:", jsonData); // Debug print
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);

      // Add filter control for experiment column
      var stringFilter = new google.visualization.ControlWrapper({
          'controlType': 'StringFilter',
          'containerId': 'tablesearch',
          'options': {
            'filterColumnLabel': 'Experiment',
            'matchType': 'any',
	    'ui': { 'cssClass': 'google-visualization-table-table'}
            }
      });

      // Table visualization
      var table = new google.visualization.ChartWrapper({
          chartType: 'Table',
          containerId: 'table',
          options: {
            showRowNumber: true,
            allowHtml: true
          }
      });

      // Bind filter to table and render
      var dashboard = new google.visualization.Dashboard(document.querySelector('#dashboard'));
      dashboard.bind([stringFilter], [table]);
      dashboard.draw(data);
    }

    /**
     * Draws the GPFS table with filter controls.
     * Uses AJAX to fetch JSON data from the server and renders a Google Table.
     * Security: Uses encodeURIComponent for user input in query string.
     */
    function drawTableGpfs(str) {
      // Fetch GPFS data for a fileset
      var jsonData = $.ajax({
          url: "gpfs_table.php?fileset=" + encodeURIComponent(str),
          dataType: "json",
          async: false
          }).responseText;
      console.log("gpfs_table.php JSON:", jsonData); // Debug print
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);

      // Add filter control for experiment column
      var stringFilter = new google.visualization.ControlWrapper({
          'controlType': 'StringFilter',
          'containerId': 'tablesearch',
          'options': {
            'filterColumnLabel': 'Experiment',
            'matchType': 'any',
            'ui': { 'cssClass': 'google-visualization-table-table'}
            }
      });

      // Table visualization
      var table = new google.visualization.ChartWrapper({
          chartType: 'Table',
          containerId: 'table',
          options: {
            showRowNumber: true,
            allowHtml: true
          }
      });

      // Bind filter to table and render
      var dashboard = new google.visualization.Dashboard(document.querySelector('#dashboard'));
      dashboard.bind([stringFilter], [table]);
      dashboard.draw(data);
    }
    
    function drawTableAll() {
      var jsonData = $.ajax({
          url: "all.php",
          dataType:"json",
          async: false
          }).responseText;
      console.log("all.php JSON:", jsonData); // Debug print
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);

      var stringFilter = new google.visualization.ControlWrapper({
          'controlType': 'StringFilter',
          'containerId': 'all_search_name',
          'options': {
            'filterColumnLabel': 'Name',
            'matchType': 'any',
            'ui': { 'cssClass': 'google-visualization-table-table'}
            }
      });
      var filesetFilter = new google.visualization.ControlWrapper({
          'controlType': 'StringFilter',
          'containerId': 'all_search_fileset',
          'options': {
            'filterColumnLabel': 'Fileset',
            'matchType': 'any',
            'ui': { 'cssClass': 'google-visualization-table-table'}
            }
      });

      var table = new google.visualization.ChartWrapper({
          chartType: 'Table',
          containerId: 'column',
          options: {
            showRowNumber: true,
            allowHtml: true,
          }
      });

      var dashboard = new google.visualization.Dashboard(document.querySelector('#dashboard'));
      var allSearchName = document.getElementById('all_search_name');
      var allSearchFileset = document.getElementById('all_search_fileset');
      var treemap = document.getElementById('treemap');
      var columnDiv = document.getElementById('column');
      if (allSearchName) allSearchName.style.height = "auto";
      if (allSearchFileset) allSearchFileset.style.height = "auto";
      if (treemap) treemap.style.height = "0";
      if (columnDiv) columnDiv.style.height = "90%";
      dashboard.bind([stringFilter,filesetFilter], [table]);
      dashboard.draw(data);
    }

    function drawColumnUsers(cluster,str) {
      var jsonData = $.ajax({
          url: "users_column.php?cluster="+cluster+"&fileset="+str,
          dataType:"json",
          async: false
          }).responseText;
      var options = {
          title: str,
          legend: { position: 'top', maxLines: 3 },
          bar: { groupWidth: '75%' },
          colors: ['red','#E6FFEA'],
          chartArea: {width: '95%', height:'80%'},
          isStacked: true
          };
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);
      data.sort({column: 1, desc:true});
      var columnDiv = document.getElementById('column');
      if (columnDiv) {
        columnDiv.style.height = "50%";
        var column = new google.visualization.ColumnChart(columnDiv);
        column.draw(data, options);
      }
    }

    function drawColumnExperiments(cluster,str) {
      var jsonData = $.ajax({
          url: "experiments_column.php?cluster="+cluster+"&fileset="+str,
          dataType:"json",
          async: false
          }).responseText;
      var options = {
          title: str,
          legend: { position: 'top', maxLines: 3 },
          bar: { groupWidth: '75%' },
          colors: ['red','#E6FFEA'],
          chartArea: {width: '95%', height:'80%'},
          isStacked: true
          };
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);
      data.sort({column: 1, desc:true});
      var columnDiv = document.getElementById('column');
      if (columnDiv) {
        columnDiv.style.height = "50%";
        var column = new google.visualization.ColumnChart(columnDiv);
        column.draw(data, options);
      }
    }

    function drawColumnGpfs(str) {
      var jsonData = $.ajax({
          url: "gpfs_column.php?fileset="+str,
          dataType:"json",
          async: false
          }).responseText;
      var options = {
          title: str,
          legend: { position: 'top', maxLines: 3 },
          bar: { groupWidth: '75%' },
          colors: ['red','#E6FFEA'],
          chartArea: {width: '95%', height:'80%'},
          isStacked: true
          };
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);
      data.sort({column: 1, desc:true});
      var columnDiv = document.getElementById('column');
      if (columnDiv) {
        columnDiv.style.height = "50%";
        var column = new google.visualization.ColumnChart(columnDiv);
        column.draw(data, options);
      }
    }

    function drawTree(cluster,str) {
      var jsonData = $.ajax({
          url: "users_tree.php?cluster="+cluster+"&fileset="+str,
          dataType:"json",
          async: false
          }).responseText;
      var options = {
          minColor: '#f00',
          minColorValue: 0,
          midColor: '#ddd',
          maxColor: '#0d0',
          maxColorValue: 100,
          headerHeight: 15,
          fontColor: 'black',
          showScale: true,
          useWeightedAverageForAggregation: true,
          generateTooltip: showFullTooltip,
          };
      var data = new google.visualization.DataTable(jsonData);
      var tree = new google.visualization.TreeMap(document.getElementById('treemap'));
      if (str == 'star-pwg'){
          document.getElementById('treemap').style.height = "50%";
          tree.draw(data,options);
      } else {
          document.getElementById('treemap').innerHTML = "";
          document.getElementById('treemap').style.height = "0px";
      }

      function showFullTooltip(row, size, value) {
          return '<div style="background:#fff; padding:10px; border-style:solid">' +
          '<span><b>' + data.getValue(row, 0) + '</b></span><br>' +
          'Total quota allocated: ' + size + ' TB<br>' +
          data.getColumnLabel(3) + ': ' + data.getValue(row,3) + '%' + ' </div>';
      }
    }

    function drawPieExperiments(cluster,str) {
      var jsonData = $.ajax({
          url: "experiments_pie.php?cluster="+cluster+"&fileset="+str,
          dataType:"json",
          async: false
          }).responseText;
      var options = {
          title: str,
          is3D:true,
          pieHole: 0.4,
          };
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);
      data.sort({column: 1, desc:true});
      document.getElementById('treemap').style.height = "50%";

      // Instantiate and draw our chart, passing in some options.
      var pie = new google.visualization.PieChart(document.getElementById('treemap'));
      pie.draw(data, options);
    }

    function drawPieGpfs(str) {
      var jsonData = $.ajax({
          url: "gpfs_pie.php?fileset="+str,
          dataType:"json",
          async: false
          }).responseText;
      var options = {
          title: str,
          is3D:true,
          pieHole: 0.4,
          };
      // Create our data table out of JSON data loaded from server.
      var data = new google.visualization.DataTable(jsonData);
      data.sort({column: 1, desc:true});
      var treemapDiv = document.getElementById('treemap');
      if (treemapDiv) {
        treemapDiv.style.height = "50%";
        var pie = new google.visualization.PieChart(treemapDiv);
        pie.draw(data, options);
      }
    }