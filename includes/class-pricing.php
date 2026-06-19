<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Pricing {

    public function __construct() {}

    public function get_chargeable_meters( $product_id, $meters ) {
        $product_min = get_post_meta( $product_id, '_wcms_min_charge_meters', true );
        if ( $product_min !== '' && $product_min !== false ) {
            $min = floatval( $product_min );
        } else {
            $min = floatval( WCMS_Settings::get( 'min_charge_meters', 1 ) );
        }
        return max( $meters, $min );
    }

    public function calculate_price( $product_id, $meters ) {
        $fixed    = floatval( get_post_meta( $product_id, '_wcms_fixed_price', true ) );
        $tiers    = get_post_meta( $product_id, '_wcms_price_tiers', true );
        $effective = $this->get_chargeable_meters( $product_id, $meters );

        if ( ! is_array( $tiers ) || empty( $tiers ) ) {
            return $fixed;
        }

        $rate = $this->get_tier_rate( $tiers, $effective );

        return $fixed + ( $effective * $rate );
    }

    private function get_tier_rate( $tiers, $meters ) {
        $rate = 0;
        foreach ( $tiers as $tier ) {
            $from = floatval( $tier['from'] );
            $to   = floatval( $tier['to'] );
            if ( $meters >= $from && $meters <= $to ) {
                $rate = floatval( $tier['price'] );
            }
        }
        if ( $rate === 0.0 && ! empty( $tiers ) ) {
            $last = end( $tiers );
            if ( $meters > floatval( $last['to'] ) ) {
                $rate = floatval( $last['price'] );
            } else {
                $first = reset( $tiers );
                $rate = floatval( $first['price'] );
            }
        }
        return $rate;
    }

    public function get_price_breakdown( $product_id, $meters ) {
        $fixed     = floatval( get_post_meta( $product_id, '_wcms_fixed_price', true ) );
        $tiers     = get_post_meta( $product_id, '_wcms_price_tiers', true );
        $effective = $this->get_chargeable_meters( $product_id, $meters );
        $rate      = $this->get_tier_rate( $tiers, $effective );
        $variable  = $effective * $rate;
        $min       = floatval( WCMS_Settings::get( 'min_charge_meters', 1 ) );
        return array(
            'fixed'          => $fixed,
            'rate'           => $rate,
            'meters'         => $meters,
            'effective_meters' => $effective,
            'min_charge'     => $min,
            'min_applied'    => $effective > $meters,
            'variable'       => $variable,
            'total'          => $fixed + $variable,
        );
    }
}
