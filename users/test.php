<!DOCTYPE html>
<html>
  <head>
    <title>My Google Earth Map</title>
    <style>
      /* Set the size of the map div */
      #map {
        height: 850px;
        width: 100%;
      }
    </style>
  </head>
  <body>
    <h3>My KML Map</h3>
    <!-- The div where the map will appear -->
    <div id="map"></div>

    <script>
      function initMap() {
        // 1. Create the map object
        var map = new google.maps.Map(document.getElementById('map'), {
          center: {lat: 0, lng: 0}, // Starting center (doesn't matter much with KML)
          zoom: 2
        });

        // 2. Load the KML Layer
        var kmlLayer = new google.maps.KmlLayer({
          url: 'https://yourwebsite.com/https://squizzzly.ph/test_db_user/users/kml/testmap.kml-map-file.kml', // REPLACE WITH YOUR URL
          map: map
        });
      }
    </script>
    <!-- Load the Google Maps API. Replace YOUR_API_KEY below. -->
    <script async defer
    src="https://maps.googleapis.com/maps/api/js?key= &callback=initMap">
    </script>
  </body>
</html>