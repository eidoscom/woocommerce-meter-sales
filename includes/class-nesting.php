<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Nesting {

    public function __construct() {}

    public function calculate( $film_width_cm, $img_w_cm, $img_h_cm, $copies, $gap_cm = 0.5, $waste_pct = 5 ) {
        $waste_pct = floatval( $waste_pct ) / 100;
        $result    = $this->calculate_raw( $film_width_cm, $img_w_cm, $img_h_cm, $copies, $gap_cm );
        $result['length_cm'] = $result['length_cm'] * ( 1 + $waste_pct );
        $result['length_m']  = $result['length_cm'] / 100;
        return $result;
    }

    private function calculate_raw( $film_width_cm, $img_w_cm, $img_h_cm, $copies, $gap_cm ) {
        $normal  = $this->try_orientation( $film_width_cm, $img_w_cm, $img_h_cm, $copies, $gap_cm );
        $rotated = $this->try_orientation( $film_width_cm, $img_h_cm, $img_w_cm, $copies, $gap_cm );

        $best = ( $normal['length_cm'] <= $rotated['length_cm'] ) ? $normal : $rotated;
        $best['rotated'] = ( $best === $rotated );

        return $best;
    }

    public function calculate_multi( $film_width_cm, $images, $gap_cm = 0.5, $waste_pct = 5 ) {
        $gap_cm          = floatval( $gap_cm );
        $waste_pct       = floatval( $waste_pct ) / 100;
        $all_results     = array();
        $total_length_cm = 0;

        foreach ( $images as $i => $img ) {
            $img_w   = floatval( $img['img_w'] );
            $img_h   = floatval( $img['img_h'] );
            $copies  = intval( $img['copies'] );
            $result  = $this->calculate_raw( $film_width_cm, $img_w, $img_h, $copies, $gap_cm );
            $all_results[] = $result;
            $total_length_cm += $result['length_cm'];
        }

        $total_length_cm = $total_length_cm * ( 1 + $waste_pct );
        $total_length_m  = $total_length_cm / 100;

        return array(
            'images'          => $all_results,
            'total_length_cm' => $total_length_cm,
            'total_length_m'  => $total_length_m,
        );
    }

    private function try_orientation( $film_width, $img_w, $img_h, $copies, $gap ) {
        $total_w = $img_w + $gap;
        $across  = $total_w > 0 ? floor( ( $film_width + $gap ) / $total_w ) : 1;
        if ( $across < 1 ) {
            $across = 1;
        }

        $rows = ceil( $copies / $across );

        $total_h = $rows * ( $img_h + $gap ) - $gap;
        if ( $total_h < 0 ) {
            $total_h = 0;
        }

        return array(
            'across'     => intval( $across ),
            'rows'       => intval( $rows ),
            'img_w'      => floatval( $img_w ),
            'img_h'      => floatval( $img_h ),
            'length_cm'  => floatval( $total_h ),
            'copies'     => intval( $copies ),
            'copies_per_row' => intval( $across ),
        );
    }
}
