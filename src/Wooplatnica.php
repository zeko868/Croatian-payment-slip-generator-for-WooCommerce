<?php

require path_join(dirname(__DIR__), 'vendor/autoload.php');

use BigFish\PDF417\PDF417;
use BigFish\PDF417\Renderers\ImageRenderer;


class Wooplatnica
{
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var array
     */
    protected $options;

    /**
     * Initialize plugin and hooks
     */
    public function __construct()
    {
        $this->domain = 'wooplatnica-croatia';

        load_plugin_textdomain($this->domain, false, path_join(basename(dirname(__DIR__)), 'languages'));

        $this->options = get_option("woocommerce_{$this->domain}_settings");

        add_filter('woocommerce_payment_gateways', array($this, 'add_wooplatnica_gateway_class'));
        if ($this->options['enabled'] === 'yes') {
            add_action("woocommerce_thankyou_{$this->domain}", array($this, 'thankyou_page'));
            add_action('woocommerce_email_after_order_table', array($this, 'email_instructions'), 10, 3);
	    // add to my account page
            add_action( 'woocommerce_view_order', array($this, 'view_order_instructions'), 10, 3);
        }

    }

    private function generate_payment_slip($image_identifier, $order, $is_for_sending) {
        $order = apply_filters("{$this->domain}_order", $order);
        $payment_slip_data = new Payment_Slip_Data();
        $payment_slip_data->currency = empty($this->options['currency']) ? $order->get_currency() : $this->options['currency'];
        $payment_slip_data->set_price(apply_filters("{$this->domain}_price", $order->get_total()));
        $payment_slip_data->sender_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $payment_slip_data->sender_address = $order->get_billing_address_1();
        $payment_slip_data->sender_city = $order->get_billing_postcode() . ' ' . $order->get_billing_city();
        $payment_slip_data->recipient_name = $this->options['recipient_name'];
        $payment_slip_data->recipient_address = $this->options['recipient_address'];
        $payment_slip_data->recipient_city = $this->options['recipient_zip_code'] . ' ' . $this->options['recipient_city'];
        $payment_slip_data->recipient_iban = $this->options['recipient_bank_account_id'];
        $payment_slip_data->recipient_callout_number = $this->replace($this->options['recipient_callout_number'], $order);
        $payment_slip_data->intent_code = $this->options['intent_code'];
        $payment_slip_data->payment_model = $this->options['payment_model'];
        $payment_slip_data->description = $this->options['payment_description'];
        
        $im = imagecreatefromjpeg(dirname(__DIR__) . '/assets/payment-slip-template.jpg');
        $font_file = dirname(__DIR__) . '/assets/times-new-roman.ttf';
        $black_color = imagecolorallocate($im, 0x30, 0x30, 0x30);
        
        imagefttext($im, 11, 0, 30, 55, $black_color, $font_file, $payment_slip_data->sender_name);
        imagefttext($im, 11, 0, 30, 75, $black_color, $font_file, $payment_slip_data->sender_address);
        imagefttext($im, 11, 0, 30, 95, $black_color, $font_file, $payment_slip_data->sender_city);
        
        $recipient_name_lines = explode("\n", $payment_slip_data->recipient_name);
        $recipient_name_num_of_lines = count($recipient_name_lines);
        for ($i = 0; $i < $recipient_name_num_of_lines; $i++) {
            imagefttext($im, 11, 0, 30, 165 + 20*$i, $black_color, $font_file, $recipient_name_lines[$i]);
        }
        imagefttext($im, 11, 0, 30, 165 + 20*$recipient_name_num_of_lines, $black_color, $font_file, $payment_slip_data->recipient_address);
        imagefttext($im, 11, 0, 30, 165 + 20*$recipient_name_num_of_lines + 20, $black_color, $font_file, $payment_slip_data->recipient_city);
                    
        $monospace_font = dirname(__DIR__) . '/assets/RobotoMono-Regular.ttf';
        
        $this->display_monospace_text_with_specific_spacing($im, 325, 49, $black_color, $monospace_font, $payment_slip_data->currency);
        $this->display_monospace_text_with_specific_spacing($im, 410, 49, $black_color, $monospace_font, str_pad('=' . str_replace(array('.', ','), '', $payment_slip_data->get_price()), 15, ' ', STR_PAD_LEFT));
        $this->display_monospace_text_with_specific_spacing($im, 325, 133, $black_color, $monospace_font, $payment_slip_data->recipient_iban);
        $this->display_monospace_text_with_specific_spacing($im, 312, 167, $black_color, $monospace_font, $payment_slip_data->recipient_callout_number);
        
        $this->display_monospace_text_with_specific_spacing($im, 226, 167, $black_color, $monospace_font, $payment_slip_data->payment_model);
        $this->display_monospace_text_with_specific_spacing($im, 226, 205, $black_color, $monospace_font, $payment_slip_data->intent_code);

        $recipient_name_lines = explode("\n", $payment_slip_data->description);
        $recipient_name_num_of_lines = count($recipient_name_lines);
        for ($i = 0; $i < $recipient_name_num_of_lines; $i++) {
            imagefttext($im, 11, 0, 360, 190 + 16*$i, $black_color, $font_file, $recipient_name_lines[$i]);
        }
        
        $this->display_right_aligned($im, 11, 870, 45, $black_color, $font_file, $payment_slip_data->currency . ' ' . str_replace('.', ',', $payment_slip_data->get_price()));
        
        $this->display_right_aligned($im, 11, 870, 134, $black_color, $font_file, $payment_slip_data->recipient_iban);
        
        $this->display_right_aligned($im, 11, 870, 167, $black_color, $font_file, $payment_slip_data->payment_model . ' ' . $payment_slip_data->recipient_callout_number);
        
        $recipient_name_lines = explode("\n", $payment_slip_data->description);
        $recipient_name_num_of_lines = count($recipient_name_lines);
        for ($i = 0; $i < $recipient_name_num_of_lines; $i++) {
            imagefttext($im, 10, 0, 655, 200 + 13*$i, $black_color, $font_file, $recipient_name_lines[$i]);
        }

        //embed_barcode($im, $payment_slip_data->encode(), 50, 250, 3, 1, $black_color);
        
        // // does not work well when point height or width is not an integer
        //function embed_barcode($image, $data_to_encode, $x, $y, $point_height, $point_width, $color)
        //{
        //    $pdf417 = new PDF417();
        //    $data = $pdf417->encode($data_to_encode);
        //
        //    $pixel_grid = $data->getpixel_grid();
        //    $num_of_rows = count($pixel_grid);
        //    for ($i = 0; $i < $data->rows; $i++) {
        //        $pixel_row = $pixel_grid[$i];
        //        $num_of_columns = count($pixel_row);
        //        for ($j = 0; $j < $num_of_columns; $j++) {
        //            if ($pixel_row[$j] === true) {
        //                for ($k = 0; $k < $point_height; $k++) {
        //                    for ($l = 0; $l < $point_width; $l++) {
        //                        imagesetpixel($image, $x + $point_width*$j + $l, $y + $point_height*$i + $k, $color);
        //                    }
        //                }
        //            }
        //        }
        //    }
        //}

        if ($this->options['display_barcode'] === 'yes') {
            $this->embed_barcode($im, $payment_slip_data->encode(), 29, 242, $black_color);
        }
        ob_start(); // Let's start output buffering.
        imagepng($im); //This would normally output the image, but because of ob_start(), it won't.
        $payment_slip_blob = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        
        $order_id = $order->get_id();
        $webapp_name = get_bloginfo('name');
        $webapp_name_for_filename = mb_ereg_replace("([\.]{2,})", '', mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $webapp_name));
        $file_name = sprintf( '%s-%s-%s', __('payment-slip', $this->domain), $webapp_name_for_filename, $order_id);
        if ($is_for_sending) {
            add_action( 'phpmailer_init', function() use ($payment_slip_blob, $image_identifier, $file_name) {
                global $phpmailer;
                $phpmailer->SMTPKeepAlive = true;
                $phpmailer->AddStringEmbeddedImage($payment_slip_blob, $image_identifier, "$file_name.png", 'base64', 'image/png');
            });
        }
        else {
            $img_element_alt = __('Payment slip image', $this->domain);
            $download_button_text = __('Download payment slip', $this->domain);
            echo '<img id="payment-slip-image" src="data:image/png;base64,';
            echo base64_encode($payment_slip_blob);
            echo "\" alt=\"$img_element_alt\"/><br/>";
            echo <<< EOS
            <button type="button" id="download-payment-slip" style="margin-bottom:10px;">$download_button_text</button>
            <script type="text/javascript">
                var fileName = '$file_name';
                const clearUrl = url => url.replace(/^data:image\/\w+?;base64,/, '');

                const downloadImage = (name, content, type) => {
                    var link = document.createElement('a');
                    link.style = 'position: fixed; left -10000px;';
                    link.href = `data:application/octet-stream;base64,\${encodeURIComponent(content)}`;
                    link.download = /\.\w+/.test(name) ? name : `\${name}.\${type}`;

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }

                var downloadButton = document.getElementById('download-payment-slip');
                downloadButton.addEventListener('click', function() {
                    var img = document.getElementById('payment-slip-image');

                    downloadImage(fileName, clearUrl(img.src), 'png');
                });
            </script>
EOS;
        }
    }

    private function display_monospace_text_with_specific_spacing($im, $x, $y, $color, $font, $text, $spacing=14.3) {
        for ($i = 0; $i < strlen($text); $i++) {
            imagefttext($im, 15, 0, $x + $spacing*$i, $y, $color, $font, $text[$i]);
        }
    }

    private function get_coord_x_for_right_alignment($font_size, $font, $text, $coord_right_x) {
        $text_coords = imagettfbbox($font_size, 0, $font, $text);
        $text_width = $text_coords[4] - $text_coords[0];
        $coord_left_x = $coord_right_x - $text_width;
        return $coord_left_x;
    }
    
    private function display_right_aligned($im, $font_size, $x, $y, $color, $font, $text) {
        imagefttext($im, $font_size, 0, $this->get_coord_x_for_right_alignment($font_size, $font, $text, $x), $y, $color, $font, $text);
    }

    private function embed_barcode($image, $data_to_encode, $x, $y, $color)
    {
        $pdf417 = new PDF417();
        $data = $pdf417->encode($data_to_encode);
    
        $renderer = new ImageRenderer([
            'format' => 'png',
            'padding' => 50
        ]);
        
        $barcode_image = $renderer->render($data)->getCore();
        $scaled_barcode_image = imagescale($barcode_image, 290, 150);
        imagecopy($image, $scaled_barcode_image, $x, $y, 25, 20, 243, 110);
    }

    /**
     * @param string   $string
     * @param WC_Order $order
     *
     * @return mixed
     */
    protected function replace($string, $order)
    {
        return str_replace([
            '%order%',
            '%date%',
            '%year%',
            '%month%',
            '%day%',
        ], [
            $order->get_id(),
            date('Y-m-d', $order->get_date_created()->getTimestamp()),
            date('Y', $order->get_date_created()->getTimestamp()),
            date('m', $order->get_date_created()->getTimestamp()),
            date('d', $order->get_date_created()->getTimestamp()),
        ], $string);
    }

    /**
     * @param array $methods
     *
     * @return array
     */
    public function add_wooplatnica_gateway_class($methods)
    {
        $methods[] = WC_Gateway_Wooplatnica::class;
        return $methods;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool     $sent_to_admin
     * @param bool     $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if (!$sent_to_admin && $this->domain === $order->get_payment_method() && $order->has_status('on-hold')) {
            if ($this->options['instructions']) {
                echo wpautop(wptexturize($this->options['instructions'])).PHP_EOL;
            }
            $image_identifier = 'payment-slip';
            $this->generate_payment_slip($image_identifier, $order, true);
            $img_element_alt = __('Problem loading image of payment slip', $this->domain);
            echo "<img src=\"cid:$image_identifier\" alt=\"$img_element_alt\"/>";
        }
    }

    /**
     * Output for the order received page.
     *
     * @param int $order_id
     */
    public function thankyou_page($order_id)
    {
        if ($this->options['description']) {
            echo wpautop(wptexturize(wp_kses_post($this->options['description'])));
        }
        $order = wc_get_order( $order_id );
        $this->generate_payment_slip(null, $order, false);
    }
     /**
     * Output for the My account -> View order page.
     */
    public function view_order_instructions($order_id) {
	// Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );
		
		if( $order->get_payment_method() === 'wooplatnica-croatia' && $order->has_status('on-hold')){
			if ($this->options['description']) {
				echo wpautop(wptexturize(wp_kses_post($this->options['description'])));
			}
			$order = wc_get_order( $order_id );
			$this->generate_payment_slip(null, $order, false);
		 }
    }
}
