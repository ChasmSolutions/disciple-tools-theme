<?php

/**
 * Class Location_Grid_Geocoder
 */
class Location_Grid_Geocoder {

    public $geojson;
    public $geometry_folder = '';
    public $mirror_source;

    public function __construct() {
        $this->geojson         = [];
        $this->geometry_folder = $this->geometry_folder();
        if ( function_exists( 'dt_get_location_grid_mirror' ) ) {
            $this->mirror_source = dt_get_location_grid_mirror();
        } else {
            $this->mirror_source = get_option( 'dt_location_grid_mirror' );
        }
    }

    /**
     * @param      $longitude
     * @param      $latitude
     * @param       $country_code
     * @param null $level
     *
     * @return array|bool|null
     */
    public function get_grid_id_by_lnglat( $longitude, $latitude, $country_code = null, $level = null ) {

        $longitude = (float) $longitude;
        $latitude = (float) $latitude;

        // get results
        if ( $level === 'admin5') { // get admin2 only
            $results = $this->query_level_by_lnglat( $longitude, $latitude, 5 );
        } else if ( $level === 'admin4') { // get admin2 only
            $results = $this->query_level_by_lnglat( $longitude, $latitude, 4 );
        } else if ( $level === 'admin3') { // get admin2 only
            $results = $this->query_level_by_lnglat( $longitude, $latitude, 3 );
        } else if ( $level === 'admin2') { // get admin2 only
            $results = $this->query_level_by_lnglat( $longitude, $latitude, 2 );
        } else if ( $level === 'admin1') { // get admin1 only
            $results = $this->query_level_by_lnglat( $longitude, $latitude, 1 );
        } else if ( $level === 'admin0') { // get country only
            $results = $this->query_level_by_lnglat( $longitude, $latitude, 0 );
        } else { // get lowest match
            $results = $this->query_lowest_level_by_lnglat( $longitude, $latitude, $country_code );
        }

        // test results

        /** Test 1: Test for exact match and return results. */
        $test1 = $this->lnglat_test1( $results );
        if ( $test1 ) {
            return $test1;
        }

        /** Test 2: Point in Polygon test to find exact match */
        $test2 = $this->lnglat_test2( $results, $longitude, $latitude );
        if ( $test2 ) {
            return $test2;
        }

        /** Test 3: Nearest Perimeter Test */
        $test3 = $this->lnglat_test3( $results, $longitude, $latitude );
        if ( $test3 ) {
            return $test3;
        }

        /** Test 4 : Center Point Test */
        $test4 = $this->lnglat_test4( $longitude, $latitude );
        if ( $test4 ) {
            return $test4;
        }

        return [];
    }

    public function get_possible_matches_by_lnglat( $longitude, $latitude, $country_code = null, $level = null ) {

        $longitude = (float) $longitude;
        $latitude = (float) $latitude;

        $results = $this->query_possible_matches_by_lnglat( $longitude, $latitude );

        dt_write_log( $results );
        return $results;
    }

    /**
     * Test 1: Test for exact match and return results.
     *
     * @param $results
     *
     * @return bool
     */
    public function lnglat_test1( $results ) {
        if ( count( $results ) === 1 && ! empty( $results ) ) {
            error_log( '1' );
            // return test 1 results
            foreach ( $results as $result ) {
                if ( ! isset( $result['grid_id'] ) ) {
                    $result = $result[0];
                }

                return $result;
            }
        }

        return false;
    }

    /**
     * Test 2: Point in Polygon test to find exact match within possible polygons.
     *
     * @param $results
     * @param $longitude
     * @param $latitude
     *
     * @return bool|array
     */
    public function lnglat_test2( $results, $longitude, $latitude ) {
        if ( count( $results ) > 1 && ! empty( $results ) ) {
            error_log( '2' );

            foreach ( $results as $result ) {
                if ( $this->_this_grid_id( (int) $result['grid_id'], $longitude, $latitude ) ) {
                    // return test 2 results
                    if ( ! isset( $result['grid_id'] ) ) {
                        $result = $result[0];
                    }

                    return $result;
                }
            }
        }

        return false;
    }

    /**
     * Test 3: Nearest Perimeter Test
     * For rare points that fall just outside of the polygon lines on coasts. This test will find the nearest
     * longitude/latitude point from the previous list of polygons.
     *
     * @param $results
     * @param $longitude
     * @param $latitude
     *
     * @return bool
     */
    public function lnglat_test3( $results, $longitude, $latitude ) {
        if ( ! empty( $this->geojson ) && ! empty( $results ) ) {
            error_log( '3' );

            $grid_id = $this->grid_id_from_nearest_polygon_line( $results, $longitude, $latitude );

            // return test 3 results
            foreach ( $results as $result ) {
                if ( (int) $result['grid_id'] === (int) $grid_id ) {
                    // return test 3 results
                    if ( ! isset( $result['grid_id'] ) ) {
                        $result = $result[0];
                    }

                    return $result;
                }
            }
        }

        return false;
    }

    /**
     * Test 4 : Center Point Test
     *
     * @param $results
     * @param $longitude
     * @param $latitude
     *
     * @return array|bool|null
     */
    public function lnglat_test4( $longitude, $latitude ) {
        global $wpdb;

        error_log( '4' );

        /**
         * No bounding set results,
         * Lng/Lat is outside all boundingboxes for administrative units
         * These are often islands, etc.
         * Therefore find the nearest center point of admin1 and admin2 to this point.
         */
        $grid_id = $this->grid_id_by_nearest_centerpoint( $longitude, $latitude );
        if ( $grid_id === false ) {
            return false;
        }

        // Return
        $result = $wpdb->get_results( $wpdb->prepare( "
            SELECT g.*, a0.name as admin0_name, a1.name as admin1_name, a2.name as admin2_name, a3.name as admin3_name, a4.name as admin4_name, a5.name as admin5_name
                FROM $wpdb->dt_location_grid as g
                LEFT JOIN $wpdb->dt_location_grid as a0 ON g.admin0_grid_id=a0.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a1 ON g.admin1_grid_id=a1.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a2 ON g.admin2_grid_id=a2.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a3 ON g.admin3_grid_id=a3.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a4 ON g.admin4_grid_id=a4.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a5 ON g.admin5_grid_id=a5.grid_id
                WHERE g.grid_id = %d;
            ", $grid_id ), ARRAY_A );

        if ( empty( $result ) ) {
            return false;
        }
        if ( ! isset( $result['grid_id'] ) ) {
            $result = $result[0];
        }

        return $result;

    }

    /**
     * @param $results
     * @param $longitude_x
     * @param $latitude_y
     *
     * @return bool|string
     */
    public function grid_id_from_nearest_polygon_line( $results, $longitude, $latitude ) {

        // get location_grid geojson from test 2
        $geojson         = $this->geojson;
        $coordinate_list = [];

        // build flat associative array of all coordinates
        foreach ( $results as $result ) {
            $grid_id  = $result['grid_id'];
            $features = $geojson[ $grid_id ]['features'];

            // handle Polygon and MultiPolygon geometries
            foreach ( $features as $feature ) {
                if ( $feature['geometry']['type'] === 'Polygon') {
                    foreach ( $feature['geometry']['coordinates'] as $coordinates ) { // select out the coordinate list

                        foreach ( $coordinates as $coordinate ) { // build flat associate array of $coordinates
                            $coordinate_list[ $grid_id ] = $coordinate;
                        }
                    }
                } else if ( $feature['geometry']['type'] === 'MultiPolygon') {
                    foreach ( $feature['geometry']['coordinates'] as $top_coordinates ) { // select out the multi polygons
                        foreach ( $top_coordinates as $coordinates ) { // select out the coordinate list

                            foreach ( $coordinates as $coordinate ) { // build flat associate array of $coordinates
                                $coordinate_list[ $grid_id ] = $coordinate;
                            }
                        }
                    }
                }
            }
        }

        // get distance between reference and all points
        $distance = [];
        foreach ( $coordinate_list as $key => $pair ) {
            $distance[ $key ] = $this->_distance( $pair[0], $pair[1], $longitude, $latitude );
        }

        asort( $distance ); // sort distances so smallest is on top
        $keys = array_keys( $distance ); // pull keys

        return $keys[0]; // return top key
    }

    /**
     * Get grid_id by matching the nearest centerpoint to provided longitude/latitude.
     *
     * @param $longitude
     * @param $latitude
     *
     * @return bool
     */
    public function grid_id_by_nearest_centerpoint( $longitude, $latitude ) {
        global $wpdb;

        // create bounding box from longitude/latitude
        $north_latitude = ceil( $latitude ) + 1;
        $south_latitude = floor( $latitude ) - 1;
        $west_longitude = floor( $longitude ) - 1;
        $east_longitude = ceil( $longitude ) + 1;

        // calculate the nearest admin2 centerpoint.
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT grid_id, longitude, latitude
            FROM $wpdb->dt_location_grid
            WHERE longitude < %f
            AND longitude > %f
            AND latitude < %f
            AND latitude > %f
            AND level > 1;
        ", $east_longitude, $west_longitude, $north_latitude, $south_latitude ), ARRAY_A );

        if ( ! empty( $results ) ) {

            $distance = [];
            foreach ( $results as $result ) {
                $distance[ $result['grid_id'] ] = $this->_distance( $result['longitude'], $result['latitude'], $longitude, $latitude );
            }
            asort( $distance ); // sort distances so smallest is on top
            $keys = array_keys( $distance ); // pull keys

            return $keys[0]; // return top key
        }

        return false;
    }

    /**
     * Downloads GeoJSON polygons and parses through geometries trying to match lon/lat within the polygons
     *
     * @param $grid_id
     * @param $longitude_x
     * @param $latitude_y
     *
     * @return int|bool
     */
    public function _this_grid_id( $grid_id, $longitude_x, $latitude_y ) {

        // get location_grid geojson
        $raw_geojson = @file_get_contents( $this->geometry_folder . $grid_id . '.geojson' );
        if ( $raw_geojson === false ) {
            $raw_geojson = @file_get_contents( $this->mirror_source . 'low/' . $grid_id . '.geojson' );
            if ( $raw_geojson === false ) {
                return false;
            }
        }
        $geojson                   = json_decode( $raw_geojson, true );
        $this->geojson[ $grid_id ] = $geojson; // save for 3 test if necessary
        $features                  = $geojson['features'];

        // handle Polygon and MultiPolygon geometries
        foreach ( $features as $feature ) {
            if ( $feature['geometry']['type'] === 'Polygon') {
                foreach ( $feature['geometry']['coordinates'] as $coordinates ) {

                    $data = $this->_split_polygon( $coordinates );

                    $vertices_x     = $data['longitude'];
                    $vertices_y     = $data['latitude'];
                    $points_polygon = count( $vertices_x );  // number vertices - zero-based array

                    if ( $this->_is_in_polygon( $points_polygon, $vertices_x, $vertices_y, $longitude_x, $latitude_y ) ) {
                        return $grid_id;
                    }
                }
            } else if ( $feature['geometry']['type'] === 'MultiPolygon') {
                foreach ( $feature['geometry']['coordinates'] as $top_coordinates ) {
                    foreach ( $top_coordinates as $coordinates ) {

                        $data = $this->_split_polygon( $coordinates );

                        $vertices_x     = $data['longitude'];
                        $vertices_y     = $data['latitude'];
                        $points_polygon = count( $vertices_x );  // number vertices - zero-based array

                        if ( $this->_is_in_polygon( $points_polygon, $vertices_x, $vertices_y, $longitude_x, $latitude_y ) ) {
                            return $grid_id;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param        $grid_id
     * @param string $type
     *
     * @return bool
     */
    public function _polygon_exists( $grid_id, $type = 'polygon') {
        if ( $type === 'polygon') {
            $ch = curl_init( $this->mirror_source . 'low/' . $grid_id . '.geojson' );
        } else if ( $type === 'polygon_collection') {
            $ch = curl_init( $this->mirror_source . 'collection/' . $grid_id . '.geojson' );
        } else {
            error_log( '_polygons_exists:: missing correct $type' );

            return false;
        }

        curl_setopt( $ch, CURLOPT_NOBODY, true );
        curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13' );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_exec( $ch );
        $retcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        curl_close( $ch );

        // $retcode >= 400 -> not found, $retcode = 200, found.
        if ( $retcode === 200 ) {
            return true;
        }

        return false;
    }

    /**
     * Takes a spilt list of lng/lats and compares with a single lng/lat to see if the single exists within the polygon
     *
     * @param $points_polygon
     * @param $vertices_x
     * @param $vertices_y
     * @param $longitude_x
     * @param $latitude_y
     *
     * @return bool|int
     */
    public function _is_in_polygon( $points_polygon, $vertices_x, $vertices_y, $longitude_x, $latitude_y ) {
        $i = $j = $c = 0;
        for ( $i = 0, $j = $points_polygon - 1; $i < $points_polygon; $j = $i ++ ) {
            if ( ( ( $vertices_y[ $i ] > $latitude_y != ( $vertices_y[ $j ] > $latitude_y ) ) && ( $longitude_x < ( $vertices_x[ $j ] - $vertices_x[ $i ] ) * ( $latitude_y - $vertices_y[ $i ] ) / ( $vertices_y[ $j ] - $vertices_y[ $i ] ) + $vertices_x[ $i ] ) ) ) {
                $c = ! $c;
            }
        }

        return $c;
    }

    /**
     * Takes the coordinates section of a geojson polygon and splits the lng/lat coordinates, so they can be used by _is_in_polygon
     *
     * @param array $polygon_geometry
     *
     * @return array
     */
    public function _split_polygon( array $polygon_geometry ) {
        $longitude = $latitude = $data = [];
        foreach ( $polygon_geometry as $vertices ) {
            $longitude[] = $vertices[0];
            $latitude[]  = $vertices[1];
        }
        $data = [
            'longitude' => $longitude,
            'latitude'  => $latitude,
        ];

        return $data;
    }

    /**
     * @link https://stackoverflow.com/questions/9589130/find-closest-longitude-and-latitude-in-array
     *
     * @param $a
     * @param $b
     *
     * @return float
     */
    public function _distance( $lon1, $lat1, $lon2, $lat2 ) {
        $theta = $lon1 - $lon2;
        $dist  = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
        $dist  = acos( $dist );
        $dist  = rad2deg( $dist );
        $miles = $dist * 60 * 1.1515;

        return $miles;
    }

    public function query_level_by_lnglat( float $longitude, float $latitude, int $level ): array {
        global $wpdb;

        $query = $wpdb->get_results( $wpdb->prepare( "
            SELECT g.*, a0.name as admin0_name, a1.name as admin1_name, a2.name as admin2_name, a3.name as admin3_name, a4.name as admin4_name, a5.name as admin5_name
            FROM $wpdb->dt_location_grid as g
            LEFT JOIN $wpdb->dt_location_grid as a0 ON g.admin0_grid_id=a0.grid_id
            LEFT JOIN $wpdb->dt_location_grid as a1 ON g.admin1_grid_id=a1.grid_id
            LEFT JOIN $wpdb->dt_location_grid as a2 ON g.admin2_grid_id=a2.grid_id
            LEFT JOIN $wpdb->dt_location_grid as a3 ON g.admin3_grid_id=a3.grid_id
            LEFT JOIN $wpdb->dt_location_grid as a4 ON g.admin4_grid_id=a4.grid_id
            LEFT JOIN $wpdb->dt_location_grid as a5 ON g.admin5_grid_id=a5.grid_id
            WHERE 
            g.north_latitude >= %f AND
            g.south_latitude <= %f AND
            g.west_longitude >= %f AND
            g.east_longitude <= %f AND
            g.level = %d
            LIMIT 10;
		", $latitude, $latitude, $longitude, $longitude, $level ), ARRAY_A );

        if ( empty( $query ) ) {
            return [];
        }

        return $query;
    }

    public function query_lowest_level_by_lnglat( float $longitude, float $latitude, string $country_code = null ): array {
        global $wpdb;

        if ( is_null( $country_code ) ) {
            dt_write_log( 'no country code' );
            $query = $wpdb->get_results( $wpdb->prepare( "
                SELECT g.*, a0.name as admin0_name, a1.name as admin1_name, a2.name as admin2_name, a3.name as admin3_name, a4.name as admin4_name, a5.name as admin5_name
                FROM $wpdb->dt_location_grid as g
                LEFT JOIN $wpdb->dt_location_grid as a0 ON g.admin0_grid_id=a0.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a1 ON g.admin1_grid_id=a1.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a2 ON g.admin2_grid_id=a2.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a3 ON g.admin3_grid_id=a3.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a4 ON g.admin4_grid_id=a4.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a5 ON g.admin5_grid_id=a5.grid_id
                WHERE
                g.north_latitude >= %f AND
                g.south_latitude <= %f AND
                g.west_longitude >= %f AND
                g.east_longitude <= %f
                ORDER BY g.level DESC
                LIMIT 10;
            ", $latitude, $latitude, $longitude, $longitude ), ARRAY_A );

            if ( empty( $query ) ) {
                return [];
            }

            // get highest level found
            $highest = 0;
            foreach ( $query as $row ) {
                if ( $row['level'] > $highest ) {
                    $highest = $row['level'];
                }
            }
            foreach ( $query as $index => $value ) {
                if ( $value['level'] < $highest ) {
                    unset( $query[$index] );
                }
            }

            return $query;

        } else { // using country_code is twice as fast.

            // get level
            $country_levels = $this->get_country_levels();
            $country_code = strtoupper( $country_code );
            $level = $country_levels[$country_code]['level'] ?? 0;

            $query = $wpdb->get_results( $wpdb->prepare( "
                SELECT g.*, a0.name as admin0_name, a1.name as admin1_name, a2.name as admin2_name, a3.name as admin3_name, a4.name as admin4_name, a5.name as admin5_name
                FROM $wpdb->dt_location_grid as g
                LEFT JOIN $wpdb->dt_location_grid as a0 ON g.admin0_grid_id=a0.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a1 ON g.admin1_grid_id=a1.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a2 ON g.admin2_grid_id=a2.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a3 ON g.admin3_grid_id=a3.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a4 ON g.admin4_grid_id=a4.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a5 ON g.admin5_grid_id=a5.grid_id
                WHERE
                g.level = %d AND
                g.north_latitude >= %f AND
                g.south_latitude <= %f AND
                g.west_longitude >= %f AND
                g.east_longitude <= %f
                ORDER BY g.level DESC
                LIMIT 10;
            ", $level, $latitude, $latitude, $longitude, $longitude ), ARRAY_A );

            if ( empty( $query ) ) {
                return [];
            }

            return $query;
        }

    }

    public function query_possible_matches_by_lnglat( float $longitude, float $latitude ): array {
        global $wpdb;

        $raw_query = $wpdb->get_results( $wpdb->prepare( "
                SELECT g.*, a0.name as admin0_name, a1.name as admin1_name, a2.name as admin2_name, a3.name as admin3_name, a4.name as admin4_name, a5.name as admin5_name
                FROM $wpdb->dt_location_grid as g
                LEFT JOIN $wpdb->dt_location_grid as a0 ON g.admin0_grid_id=a0.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a1 ON g.admin1_grid_id=a1.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a2 ON g.admin2_grid_id=a2.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a3 ON g.admin3_grid_id=a3.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a4 ON g.admin4_grid_id=a4.grid_id
                LEFT JOIN $wpdb->dt_location_grid as a5 ON g.admin5_grid_id=a5.grid_id
                WHERE
                g.north_latitude >= %f AND
                g.south_latitude <= %f AND
                g.west_longitude >= %f AND
                g.east_longitude <= %f
                ORDER BY g.level DESC
                LIMIT 15;
            ", $latitude, $latitude, $longitude, $longitude ), ARRAY_A );

        if ( empty( $raw_query ) ) {
            return [];
        }
        foreach ( $raw_query as $row ) {
            $query[$row['grid_id']] = $row;
        }

        // get highest level found
        $highest = 0;
        foreach ( $query as $row ) {
            if ( $row['level'] > $highest ) {
                $highest = $row['level'];
            }
        }


        $results = [];

        foreach( $query as $result ) {
            if ( $result['level'] === $highest ) {
                $results['grid_id'] = $result;

                $results['admin0_grid_id'] = $query[$result['grid_id']]; // @todo unfinished
            }
        }

        return $query;

    }

    public function get_country_levels( $reset = false ) : array {
        if ( $reset ) {
            delete_option( 'dt_location_grid_country_levels' );
        }

        $country_levels = get_option( 'dt_location_grid_country_levels' );

        if ( empty( $country_levels ) ) {
            global $wpdb;
            $query = $wpdb->get_results( "
                SELECT g.country_code, g.admin0_code, MAX(g.level) as level
                FROM $wpdb->dt_location_grid as g
                WHERE g.level < 10 
                GROUP BY g.admin0_code, g.country_code;
            ", ARRAY_A );
            if ( empty( $query ) ) {
                error_log( 'No location records found. You must install location_grid database.' );
                return [];
            }
            $country_levels = [];
            foreach ( $query as $country ) {
                if ( ! empty( $country['country_code'] ) ) {
                    $country_levels[$country['country_code']] = $country;
                }
            }
            update_option( 'dt_location_grid_country_levels', $country_levels, false );
        }

        return $country_levels;
    }

    public function geometry_folder() {
        $dir         = wp_upload_dir();
        $uploads_dir = trailingslashit( $dir['basedir'] );
        if ( ! file_exists( $uploads_dir . 'location_grid' ) ) {
            mkdir( $uploads_dir . 'location_grid' );
        }

        return $uploads_dir . 'location_grid/';
    }

}
