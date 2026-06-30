<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Imposition_PDF {

	public static function generate( $order_id ) {
		if ( ! class_exists( 'FPDF' ) ) {
			require_once WCMS_PLUGIN_DIR . 'lib/fpdf/fpdf.php';
		}
		if ( ! class_exists( 'setasign\\Fpdi\\Fpdi' ) ) {
			require_once WCMS_PLUGIN_DIR . 'lib/fpdi/autoload.php';
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$all_images_data = array();
		$all_file_paths  = array();
		$film_width      = 57;
		$gap             = 0.5;
		$opt_layout      = null; // strip-packed rows from JS

		foreach ( $order->get_items() as $item ) {
			$raw_images = $item->get_meta( '_wcms_images_data' );
			$file_paths = $item->get_meta( '_wcms_upload_paths' );
			$raw_data   = $item->get_meta( '_wcms_raw_data' );

			if ( $raw_data ) {
				$rd = is_string( $raw_data ) ? json_decode( $raw_data, true ) : $raw_data;
				if ( is_array( $rd ) && ! empty( $rd['optimized'] ) && isset( $rd['rows'] ) ) {
					$opt_layout = $rd;
				}
			}

			if ( $raw_images ) {
				$decoded = is_string( $raw_images ) ? json_decode( $raw_images, true ) : $raw_images;
				if ( is_array( $decoded ) ) {
					if ( ! empty( $decoded['optimized'] ) && isset( $decoded['images'] ) ) {
						$all_images_data = array_merge( $all_images_data, $decoded['images'] );
					} else {
						$all_images_data = array_merge( $all_images_data, $decoded );
					}
				}
				$pid = $item->get_product_id();
				$fw = get_post_meta( $pid, '_wcms_film_width', true );
				if ( $fw ) {
					$film_width = floatval( $fw );
				}
				$g = get_post_meta( $pid, '_wcms_gap', true );
				if ( $g !== '' ) {
					$gap = floatval( $g );
				}
			}
			if ( $file_paths && is_array( $file_paths ) ) {
				$all_file_paths = array_merge( $all_file_paths, $file_paths );
			}
		}

		if ( empty( $all_images_data ) ) {
			return false;
		}

		$gap_mm    = $gap * 10;
		$page_w_mm = $film_width * 10;

		$colors = array(
			array( 231, 76, 60 ),
			array( 52, 152, 219 ),
			array( 46, 204, 113 ),
			array( 243, 156, 18 ),
			array( 155, 89, 182 ),
		);

		if ( $opt_layout ) {
			$rows      = $opt_layout['rows'];
			$total_length_cm = isset( $opt_layout['length_cm'] ) ? floatval( $opt_layout['length_cm'] ) : 0;
			if ( $total_length_cm <= 0 ) {
				$total_length_cm = isset( $opt_layout['total_length_m'] ) ? floatval( $opt_layout['total_length_m'] ) * 100 : 0;
			}
			$page_h_mm = max( $total_length_cm * 10 + 12, 50 );

			$pdf = new \setasign\Fpdi\Fpdi( 'P', 'mm', array( $page_w_mm, $page_h_mm ) );
			$pdf->SetAutoPageBreak( false, 0 );
			$pdf->AddPage();

			$current_y = 0;
			$all_placed = 0;

			foreach ( $rows as $ri => $row ) {
				if ( $ri > 0 ) {
					$current_y += $gap_mm;
				}
				$row_h_mm = $row['height'] * 10;

				foreach ( $row['items'] as $item ) {
					$idx     = intval( $item['imgIdx'] );
					$x       = $item['x'] * 10;
					$y       = $current_y;
					$w       = $item['w'] * 10;
					$h       = $item['h'] * 10;
					$fpath   = isset( $all_file_paths[ $idx ] ) ? $all_file_paths[ $idx ] : '';
					$c       = $colors[ $idx % count( $colors ) ];

					self::render_rect( $pdf, $fpath, $x, $y, $w, $h, $c );
					$all_placed++;
				}

				$current_y += $row_h_mm;
			}

			$draw_h = $current_y;
			$total_m = $total_length_cm / 100;
			$footer = sprintf( 'Film: %d cm | Total: %.2f m (optimized) | %d items',
				$film_width, $total_m, $all_placed
			);
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->Text( 1, $draw_h + 4, $footer );
		} else {
			$nesting = new WCMS_Nesting();
			$result  = $nesting->calculate_multi( $film_width, $all_images_data, $gap, 0 );
			if ( empty( $result['images'] ) ) {
				return false;
			}
			$images_nesting   = $result['images'];
			$total_length_cm  = $result['total_length_cm'];

			$extra_gap_total = max( count( $images_nesting ) - 1, 0 ) * $gap_mm;
			$page_h_mm = max( $total_length_cm * 10 + $extra_gap_total + 12, 50 );

			$pdf = new \setasign\Fpdi\Fpdi( 'P', 'mm', array( $page_w_mm, $page_h_mm ) );
			$pdf->SetAutoPageBreak( false, 0 );
			$pdf->AddPage();

			$draw_h = $total_length_cm * 10 + $extra_gap_total;
			$current_y = 0;

			for ( $idx = 0; $idx < count( $images_nesting ); $idx++ ) {
				$n = $images_nesting[ $idx ];
				$file_path = isset( $all_file_paths[ $idx ] ) ? $all_file_paths[ $idx ] : '';
				$c = $colors[ $idx % count( $colors ) ];

				if ( $idx > 0 ) {
					$current_y += $gap_mm;
				}

				$label = sprintf( '%s: %d x %d cm x%d',
					isset( $all_images_data[ $idx ]['title'] ) ? $all_images_data[ $idx ]['title'] : ( 'Image ' . ( $idx + 1 ) ),
					$n['img_w'], $n['img_h'], $n['copies']
				);
				$pdf->SetFont( 'Helvetica', '', 8 );
				$pdf->SetTextColor( 0, 0, 0 );
				$pdf->Text( 1, $current_y - 1, $label );

				$is_rotated = ! empty( $n['rotated'] );

				for ( $row = 0; $row < $n['rows']; $row++ ) {
					for ( $col = 0; $col < $n['across']; $col++ ) {
						$x = $col * ( $n['img_w'] * 10 + $gap_mm );
						$y = $current_y + $row * ( $n['img_h'] * 10 + $gap_mm );
						$w = $n['img_w'] * 10;
						$h = $n['img_h'] * 10;

						self::render_rect( $pdf, $file_path, $x, $y, $w, $h, $c, $is_rotated );
					}
				}

				$current_y += $n['length_cm'] * 10;
			}

			$total_m = $total_length_cm / 100;
			$footer = sprintf( 'Film: %d cm | Total: %.2f m | %d images',
				$film_width, $total_m, count( $all_images_data )
			);
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->Text( 1, $draw_h + 4, $footer );
		}

		$upload_dir = wp_upload_dir();
		$pdf_path   = $upload_dir['basedir'] . '/wcms-impositions/imposition-order-' . $order_id . '.pdf';
		wp_mkdir_p( dirname( $pdf_path ) );

		$pdf->Output( 'F', $pdf_path );
		return $pdf_path;
	}

	private static function render_rect( $pdf, $file_path, $x, $y, $w, $h, $color, $rotated = false ) {
		if ( $file_path && @file_exists( $file_path ) ) {
			try {
				self::place_content( $pdf, $file_path, $x, $y, $w, $h, $rotated );
			} catch ( \Throwable $e ) {
				self::draw_placeholder( $pdf, $x, $y, $w, $h, $color );
			}
		} else {
			self::draw_placeholder( $pdf, $x, $y, $w, $h, $color );
		}
		$pdf->SetDrawColor( 0, 0, 0 );
		$pdf->SetLineWidth( 0.2 );
		$pdf->Rect( $x, $y, $w, $h, 'D' );
	}

	private static function place_content( $pdf, $file_path, $x, $y, $w, $h, $rotated ) {
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$GLOBALS['_wcms_temp_files'] = isset( $GLOBALS['_wcms_temp_files'] ) ? $GLOBALS['_wcms_temp_files'] : array();

		if ( $ext === 'png' || $ext === 'jpg' || $ext === 'jpeg' ) {
			if ( $rotated ) {
				$tmp = self::rotate_image_file( $file_path );
				if ( $tmp !== $file_path ) {
					$GLOBALS['_wcms_temp_files'][] = $tmp;
					$file_path = $tmp;
				}
				$pdf->Image( $file_path, $x, $y, $w, $h );
			} else {
				$pdf->Image( $file_path, $x, $y, $w, $h );
			}
			return;
		}

		if ( $ext === 'pdf' ) {
			try {
				$pageCount = $pdf->setSourceFile( $file_path );
				if ( $pageCount > 0 ) {
					$tplId = $pdf->importPage( 1 );
					if ( $rotated ) {
						$cx = ( $x + $w / 2 ) * $pdf->k;
						$cy = ( $pdf->h - ( $y + $h / 2 ) ) * $pdf->k;
						$pdf->_out( 'q' );
						$pdf->_out( sprintf( '1 0 0 1 %.2F %.2F cm', $cx, $cy ) );
						$pdf->_out( '0 1 -1 0 0 0 cm' );
						$pdf->useTemplate( $tplId, -$w / 2, -$h / 2, $w, $h );
						$pdf->_out( 'Q' );
					} else {
						$pdf->useTemplate( $tplId, $x, $y, $w, $h );
					}
					return;
				}
			} catch ( \Throwable $e ) {
				$fallback = self::convert_pdf_to_png( $file_path );
				if ( $fallback ) {
					$GLOBALS['_wcms_temp_files'][] = $fallback;
					if ( $rotated ) {
						$tmp = self::rotate_image_file( $fallback );
						if ( $tmp !== $fallback ) {
							$GLOBALS['_wcms_temp_files'][] = $tmp;
							$fallback = $tmp;
						}
					}
					$pdf->Image( $fallback, $x, $y, $w, $h );
					return;
				}
			}
		}

		self::draw_placeholder( $pdf, $x, $y, $w, $h, array( 150, 150, 150 ) );
	}

	private static function convert_pdf_to_png( $file_path ) {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$cmd = 'which pdftoppm 2>/dev/null';
		$out = array();
		$ret = 0;
		exec( $cmd, $out, $ret );
		if ( $ret !== 0 || empty( $out[0] ) ) {
			$gd_ok = function_exists( 'imagecreatefromjpeg' ) || function_exists( 'imagecreatefrompng' );
			if ( $gd_ok && class_exists( 'Imagick' ) ) {
				try {
					$imagick = new Imagick( $file_path . '[0]' );
					$imagick->setImageFormat( 'png' );
					$imagick->setImageResolution( 150, 150 );
					$tmp = tempnam( sys_get_temp_dir(), 'wcms_pdf_' ) . '.png';
					$imagick->writeImage( $tmp );
					$imagick->clear();
					return $tmp;
				} catch ( \Throwable $e ) {
					return false;
				}
			}
			return false;
		}
		$pdftoppm = trim( $out[0] );
		$tmp = tempnam( sys_get_temp_dir(), 'wcms_pdf_' );
		$png = $tmp . '.png';
		$cmd = sprintf( '%s -png -r 150 -singlefile "%s" "%s" 2>/dev/null',
			escapeshellcmd( $pdftoppm ),
			escapeshellarg( $file_path ),
			escapeshellarg( $tmp )
		);
		exec( $cmd, $out2, $ret2 );
		if ( $ret2 === 0 && file_exists( $png ) ) {
			return $png;
		}
		@unlink( $tmp );
		return false;
	}

	private static function rotate_image_file( $file_path ) {
		if ( ! function_exists( 'imagecreatefromjpeg' ) && ! function_exists( 'imagecreatefrompng' ) ) {
			return $file_path;
		}
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( $ext === 'jpg' || $ext === 'jpeg' ) {
			$src = @imagecreatefromjpeg( $file_path );
		} elseif ( $ext === 'png' ) {
			$src = @imagecreatefrompng( $file_path );
		} else {
			return $file_path;
		}
		if ( ! $src ) {
			return $file_path;
		}
		$rotated = imagerotate( $src, -90, 0 );
		imagedestroy( $src );
		if ( ! $rotated ) {
			return $file_path;
		}
		$tmp = tempnam( sys_get_temp_dir(), 'wcms_rot_' ) . '.png';
		imagepng( $rotated, $tmp );
		imagedestroy( $rotated );
		return $tmp;
	}

	private static function draw_placeholder( $pdf, $x, $y, $w, $h, $color ) {
		list( $r, $g, $b ) = $color;
		$pdf->SetFillColor( $r, $g, $b );
		$pdf->Rect( $x, $y, $w, $h, 'F' );
		$pdf->SetFont( 'Helvetica', '', 6 );
		$pdf->SetTextColor( 0, 0, 0 );
		$label = round( $w / 10, 1 ) . 'x' . round( $h / 10, 1 );
		$tw = $pdf->GetStringWidth( $label );
		$tx = $x + ( $w - $tw ) / 2;
		$ty = $y + $h / 2 - 2;
		if ( $tx > $x && $ty > $y ) {
			$pdf->Text( $tx, $ty, $label );
		}
	}

	public static function download( $order_id ) {
		$GLOBALS['_wcms_temp_files'] = array();
		$pdf_path = self::generate( $order_id );
		foreach ( $GLOBALS['_wcms_temp_files'] as $tf ) {
			@unlink( $tf );
		}
		unset( $GLOBALS['_wcms_temp_files'] );
		if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
			wp_die( 'Could not generate imposition PDF for order ' . intval( $order_id ) . '.' );
		}
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="imposition-order-' . intval( $order_id ) . '.pdf"' );
		header( 'Content-Length: ' . filesize( $pdf_path ) );
		readfile( $pdf_path );
		exit;
	}
}
