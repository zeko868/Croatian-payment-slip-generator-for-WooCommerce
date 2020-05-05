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
            add_action( 'woocommerce_view_order', array($this, 'view_order_instructions'));
        }

    }

    private function display_payment_slip($order, $is_for_sending) {
        $default_options = array(   // add here default values of options that were introduced after initial version (a.k.a. version 1.0) of this plugin
            'display_confirmation_part' => 'yes',
            'payment_slip_type'         => 'national',
            'main_font'                 => 'proportional',
            'output_image_type'         => 'png'
        );
        $this->options = array_merge($default_options, $this->options); // this is useful because after updating plugin, options that didn't exist in previous version of plugin are not yet stored in the database, i.e. when those options would be fetched, their values would be null even if those newly defined options have defined default values in WC_Gateway_Wooplatnica.php, what resulted in unexcepted aad buggy behavior

        session_start();
        if (isset($_SESSION['payment-slip-image'])) {
            $payment_slip_blob = $_SESSION['payment-slip-image'];
            unset($_SESSION['payment-slip-image']);
        }
        else {
            $payment_slip_blob = $this->generate_payment_slip($order);
        }

        $order_id = $order->get_order_number();
        $webapp_name = get_bloginfo('name');
        $webapp_name_for_filename = mb_ereg_replace("([\.]{2,})", '', mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $webapp_name));
        $file_name = sprintf( '%s-%s-%s', __('payment-slip', $this->domain), $webapp_name_for_filename, $order_id);
        $image_type = $this->options['output_image_type'];
        if ($is_for_sending) {
            $image_identifier = 'payment-slip';
            $img_element_alt = __('Problem loading image of payment slip', $this->domain);
            echo "<img src=\"cid:$image_identifier\" alt=\"$img_element_alt\"/>";

            add_action( 'phpmailer_init', function() use ($payment_slip_blob, $image_identifier, $file_name) {
                global $phpmailer;
                $phpmailer->SMTPKeepAlive = true;
                $phpmailer->AddStringEmbeddedImage($payment_slip_blob, $image_identifier, "$file_name.$image_type", 'base64', "image/$image_type");
            });

            $_SESSION['payment-slip-image'] = $payment_slip_blob;
        }
        else {
            $img_element_alt = __('Payment slip image', $this->domain);
            $download_button_text = __('Download payment slip', $this->domain);

            if ($this->options['display_confirmation_part'] === 'yes') {
                $payment_slip_image_crop_right_length = '7.5%';
            }
            else {
                $payment_slip_image_crop_right_length = '34%';
            }

            $payment_slip_image_data_uri = "data:image/$image_type;base64," . base64_encode($payment_slip_blob);
            echo <<< EOS
            <div id="payment-slip-image" style="overflow: hidden">
                <div style="height: 100%">
                    <div>
                        <img src="$payment_slip_image_data_uri" alt="$img_element_alt" onload="cropPaymentSlipImage(this)"/>
                    </div>
                </div>
            </div>
            <button type="button" id="download-payment-slip" style="margin-top: 5px;">$download_button_text</button>
            <script type="text/javascript">
                function cropPaymentSlipImage(imgElem) {
                    if (imgElem.src !== '$payment_slip_image_data_uri') {     // instructions within 'else' block should be executed when the image of the payment slip is loaded and as this method is called on the load event of 'img' element, it seems that image should be loaded at the point when this method is performed. However, that's not the case when the images are loaded lazily (e.g. by WP Rocket's LazyLoad plugin) in Microsoft Edge (not in any Internet Explorer nor in Chromium-based Microsoft Edge), therefore it is checked whether the image source of 'img' HTML element is equal to the data URI of the image with the generated payment slip
                        setTimeout(function() {
                            cropPaymentSlipImage(imgElem);
                        }, 300);
                    }
                    else {
                        imgElem.style.marginTop = '-48.4%';
                        imgElem.style.marginBottom = '-58%';
                        imgElem.style.marginLeft = '-4%';
                        imgElem.style.position = 'relative';
                        imgElem.style.right = '-$payment_slip_image_crop_right_length';
                        var divParent = imgElem.parentNode;
                        divParent.style.overflow = 'hidden';
                        divParent.style.position = 'relative';
                        divParent.style.right = '$payment_slip_image_crop_right_length';
                    }
                }

                var fileName = '$file_name';
                var imageType = '$image_type';

                function clearUrl(url) {
                    return url.match(/^data:image\/\w+?;base64,(.+)$/)[1];
                }

                function convertBase64StringToBlob(b64Data, contentType) {
                    const sliceSize = 512;
                    const byteCharacters = atob(b64Data);
                    const byteArrays = [];
                  
                    for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
                        const slice = byteCharacters.slice(offset, offset + sliceSize);
                    
                        const byteNumbers = new Array(slice.length);
                        for (let i = 0; i < slice.length; i++) {
                            byteNumbers[i] = slice.charCodeAt(i);
                        }
                    
                        const byteArray = new Uint8Array(byteNumbers);
                        byteArrays.push(byteArray);
                    }
                  
                    const blob = new Blob(byteArrays, {type: contentType});
                    return blob;
                }

                function downloadImage(name, content, type) {
                    var fullName = name + '.' + type;
                    if (navigator.msSaveBlob) {     // Internet Explorer 10+
                        var contentType = 'image/' + type;
                        navigator.msSaveBlob(convertBase64StringToBlob(content, contentType), fullName); 
                    }
                    else {
                        jQuery("<a/>", {
                            "href": "data:application/octet-stream;base64," + encodeURIComponent(content),
                            "download": fullName
                        })[0].click();
                    }
                }

                jQuery("#download-payment-slip").click(function() {
                    var imageData = clearUrl(jQuery("#payment-slip-image img").prop("src"));
                    downloadImage(fileName, imageData, imageType);
                });
            </script>
EOS;
        }
    }

    private function generate_payment_slip($order) {
        $order = apply_filters("{$this->domain}_order", $order);
        $payment_slip_data = new Payment_Slip_Data();
        $payment_slip_data->currency = empty($this->options['currency']) ? $order->get_currency() : $this->options['currency'];
        $payment_slip_data->set_price(apply_filters("{$this->domain}_price", $order->get_total()));
        if (empty(get_post_meta( $order->get_id(), 'R1 račun', true ))) {
            $payment_slip_data->sender_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $payment_slip_data->sender_address = $order->get_billing_address_1();
            $payment_slip_data->sender_city = $order->get_billing_postcode() . ' ' . $order->get_billing_city();
        }
        else {
            $payment_slip_data->sender_name = get_post_meta( $order->get_id(), 'Ime tvrtke', true);
            $company_address = preg_replace('/,\s*/', "\n", get_post_meta( $order->get_id(), 'Adresa tvrtke', true), 1);
            if (strpos($company_address, "\n") === false) {
                $company_address = preg_replace('/(.+?(?:\d+(?![\d\.])\s*(?:(?:-\s*|\/\s*)?[A-Za-z]\b)?|(?i)b\.?\s*b\.?\s*(?-i)))\s*(?:-\s*)?/', "$1\n", $company_address, 1);   // take a look at samples: https://regex101.com/r/kMmIl8/1
            }
            $payment_slip_data->sender_address = $company_address;
            $payment_slip_data->sender_city = get_post_meta( $order->get_id(), 'OIB tvrtke', true);
        }
        $payment_slip_data->recipient_name = $this->options['recipient_name'];
        $payment_slip_data->recipient_address = $this->options['recipient_address'];
        $payment_slip_data->recipient_city = $this->options['recipient_zip_code'] . ' ' . $this->options['recipient_city'];
        $payment_slip_data->recipient_iban = $this->options['recipient_bank_account_id'];
        $payment_slip_data->recipient_callout_number = $this->replace($this->options['recipient_callout_number'], $order);
        $payment_slip_data->intent_code = $this->options['intent_code'];
        $payment_slip_data->payment_model = $this->options['payment_model'];
        $payment_slip_data->description = $this->replace($this->options['payment_description'], $order);
        
        switch ($this->options['payment_slip_type']) {
            case 'universal':
                $input_payment_slip_image_name = 'payment-slip-template-hub-3.png';
                $recipient_iban_x_position = 300;
                break;
            case 'national':
                $input_payment_slip_image_name = 'payment-slip-template-hub-3a.png';
                $recipient_iban_x_position = 632;
                break;
        }
        $im = imagecreatefrompng(dirname(__DIR__) . "/assets/$input_payment_slip_image_name");

        $proportional_font = dirname(__DIR__) . '/assets/times-new-roman.ttf';
        $monospaced_font = dirname(__DIR__) . '/assets/RobotoMono-Regular.ttf';
        switch ($this->options['main_font']) {
            case 'monospaced':
                $main_font = $monospaced_font;
                $font_size_left = 16;
                $font_size_right = 16;
                if (strlen($payment_slip_data->recipient_iban) >= 32) {
                    $font_size_right_iban = 14;
                }
                else {
                    $font_size_right_iban = $font_size_right;
                }
                break;
            case 'proportional':
                $main_font = $proportional_font;
                $font_size_left = 20;
                $font_size_right = 18;
                if (strlen($payment_slip_data->recipient_iban) >= 30) {
                    $font_size_right_iban = 15;
                }
                else {
                    $font_size_right_iban = $font_size_right;
                }
                break;
        }
        
        $black_color = imagecolorallocate($im, 0x30, 0x30, 0x30);
        
        imagefttext($im, $font_size_left, 0, 100, 903, $black_color, $main_font, $payment_slip_data->sender_name);
        $sender_address_lines = explode("\n", $payment_slip_data->sender_address);
        $sender_address_num_of_lines = count($sender_address_lines);
        $sender_address_y_position = 938 - 5 * ($sender_address_num_of_lines - 1);
        $sender_address_line_height = 35 - 5 * ($sender_address_num_of_lines - 1);
        for ($i = 0; $i < $sender_address_num_of_lines; $i++) {
            imagefttext($im, $font_size_left, 0, 100, $sender_address_y_position + $sender_address_line_height*$i, $black_color, $main_font, $sender_address_lines[$i]);
        }
        imagefttext($im, $font_size_left, 0, 100, $sender_address_y_position + $sender_address_line_height*$i, $black_color, $main_font, $payment_slip_data->sender_city);
        
        $recipient_name_lines = explode("\n", $payment_slip_data->recipient_name);
        $recipient_name_num_of_lines = count($recipient_name_lines);
        for ($i = 0; $i < $recipient_name_num_of_lines; $i++) {
            imagefttext($im, $font_size_left, 0, 100, 1100 + 35*$i, $black_color, $main_font, $recipient_name_lines[$i]);
        }
        imagefttext($im, $font_size_left, 0, 100, 1100 + 35*$recipient_name_num_of_lines, $black_color, $main_font, $payment_slip_data->recipient_address);
        imagefttext($im, $font_size_left, 0, 100, 1100 + 35*$recipient_name_num_of_lines + 35, $black_color, $main_font, $payment_slip_data->recipient_city);
        
        $this->display_monospace_text_with_specific_spacing($im, 632, 892, $black_color, $monospaced_font, $payment_slip_data->currency);
        $this->display_monospace_text_with_specific_spacing($im, 785, 892, $black_color, $monospaced_font, str_pad('=' . str_replace(array('.', ','), '', $payment_slip_data->get_price()), 15, ' ', STR_PAD_LEFT));
        $this->display_monospace_text_with_specific_spacing($im, $recipient_iban_x_position, 1045, $black_color, $monospaced_font, $payment_slip_data->recipient_iban);
        $this->display_monospace_text_with_specific_spacing($im, 606, 1105, $black_color, $monospaced_font, $payment_slip_data->recipient_callout_number);
        
        $this->display_monospace_text_with_specific_spacing($im, 453, 1105, $black_color, $monospaced_font, $payment_slip_data->payment_model);
        $this->display_monospace_text_with_specific_spacing($im, 453, 1173, $black_color, $monospaced_font, $payment_slip_data->intent_code);

        $description_lines = explode("\n", $payment_slip_data->description);
        $description_num_of_lines = count($description_lines);
        for ($i = 0; $i < $description_num_of_lines; $i++) {
            imagefttext($im, $font_size_left, 0, 690, 1145 + 30*$i, $black_color, $main_font, $description_lines[$i]);
        }

        if ($this->options['payment_slip_type'] === 'universal') {
            $this->display_monospace_text_with_specific_spacing($im, 107, 1295, $black_color, $monospaced_font, $this->options['recipient_swift_code'], 22.8);
            if (!empty($this->options['recipient_person_type'])) {
                switch($this->options['recipient_person_type']) {
                    case 'natural':
                        $recipient_person_type_position_x = 457;
                        break;
                    case 'legal':
                        $recipient_person_type_position_x = 502;
                        break;
                }
                $this->display_monospace_text_with_specific_spacing($im, $recipient_person_type_position_x, 1295, $black_color, $monospaced_font, 'X');
            }

            $recipient_bank_name_lines = explode("\n", $this->options['recipient_bank_name']);
            $recipient_bank_name_num_of_lines = count($recipient_bank_name_lines);
            for ($i = 0; $i < $recipient_bank_name_num_of_lines; $i++) {
                imagefttext($im, $font_size_left, 0, 110, 1325 + 31*$i, $black_color, $main_font, $recipient_bank_name_lines[$i]);
            }

            $this->display_monospace_text_with_specific_spacing($im, 177, 1432, $black_color, $monospaced_font, $this->options['sepa_transfer_currency'], 24);
            if (!empty($this->options['swift_charge_option'])) {
                switch($this->options['swift_charge_option']) {
                    case 'BEN':
                        $swift_charge_option_position_x = 412;
                        break;
                    case 'SHA':
                        $swift_charge_option_position_x = 457;
                        break;
                    case 'OUR':
                        $swift_charge_option_position_x = 502;
                        break;
                }
                $this->display_monospace_text_with_specific_spacing($im, $swift_charge_option_position_x, 1434, $black_color, $monospaced_font, 'X');
            }
        }
        
        if ($this->options['display_confirmation_part'] === 'yes') {
            $this->display_right_aligned($im, $font_size_right, 1615, 892, $black_color, $main_font, $payment_slip_data->currency . ' ' . str_replace('.', ',', $payment_slip_data->get_price()));
            
            $this->display_right_aligned($im, $font_size_right_iban, 1615, 1045, $black_color, $main_font, $payment_slip_data->recipient_iban);
            
            $this->display_right_aligned($im, $font_size_right, 1615, 1105, $black_color, $main_font, $payment_slip_data->payment_model . ' ' . $payment_slip_data->recipient_callout_number);
            
            $description_lines = explode("\n", $payment_slip_data->description);
            $description_num_of_lines = count($description_lines);
            for ($i = 0; $i < $description_num_of_lines; $i++) {
                imagefttext($im, $font_size_right, 0, 1223, 1155 + 25*$i, $black_color, $main_font, $description_lines[$i]);
            }
        }
        else {
            // hide confirmation part of the payment slip
            imagefilledrectangle($im, 1191, 750, 1700, 1630, 0);
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

        if ($this->options['payment_slip_type'] === 'national' && $this->options['display_barcode'] === 'yes') {
            $this->embed_barcode($im, $payment_slip_data->encode());
        }
        if (in_array($this->options['output_image_type'], ['jpeg', 'bmp'])) {    // image types without alpha channels
            imagecolorset($im, 0, 255, 255, 255, 0x7f);   // otherwise the background color is somehow indigo-blue
        }
        ob_start(); // Let's start output buffering.
        $image_saving_method = 'image' . $this->options['output_image_type'];
        call_user_func($image_saving_method, $im); //This would normally output the image, but because of ob_start(), it won't.
        $payment_slip_blob = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        
        return $payment_slip_blob;
    }

    private function display_monospace_text_with_specific_spacing($im, $x, $y, $color, $font, $text, $spacing=25.5) {
        for ($i = 0; $i < strlen($text); $i++) {
            imagefttext($im, 28, 0, $x + $spacing*$i, $y, $color, $font, $text[$i]);
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

    private function embed_barcode($image, $data_to_encode)
    {
        $pdf417 = new PDF417();
        $data = $pdf417->encode($data_to_encode);
    
        $renderer = new ImageRenderer([
            'format' => 'png',
            'ratio' => 2
        ]);
        
        $barcode_image = $renderer->render($data)->getCore();
        $barcode_image_width = imagesx($barcode_image);
        $barcode_image_height = imagesy($barcode_image);

        $payment_slip_barcode_area_start_x = 97;
        $payment_slip_barcode_area_end_x = 538;
        $payment_slip_barcode_area_start_y = 1241;
        $payment_slip_barcode_area_end_y = 1439;
        $payment_slip_barcode_area_width = $payment_slip_barcode_area_end_x - $payment_slip_barcode_area_start_x;
        $payment_slip_barcode_area_height = $payment_slip_barcode_area_end_y - $payment_slip_barcode_area_start_y;

        if ($payment_slip_barcode_area_width < $barcode_image_width) {
            $barcode_image = imagescale($barcode_image, $payment_slip_barcode_area_width);
            $barcode_image_width = imagesx($barcode_image);
            $barcode_image_height = imagesy($barcode_image);
        }

        $payment_slip_exact_location_for_centered_barcode_x = $payment_slip_barcode_area_start_x + (($payment_slip_barcode_area_width - $barcode_image_width) / 2);
        $payment_slip_exact_location_for_centered_barcode_y = $payment_slip_barcode_area_start_y + (($payment_slip_barcode_area_height - $barcode_image_height) / 2);
        imagecopy($image, $barcode_image, $payment_slip_exact_location_for_centered_barcode_x, $payment_slip_exact_location_for_centered_barcode_y, 0, 0, $barcode_image_width, $barcode_image_height);
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
            $order->get_order_number(),
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
        $initial_order_status = get_option("woocommerce_{$this->domain}_settings")['order_status'];
        if (!$sent_to_admin && $this->domain === $order->get_payment_method() && $order->has_status(ltrim($initial_order_status, 'wc-'))) {
            if ($this->options['instructions']) {
                echo wpautop(wptexturize($this->options['instructions'])).PHP_EOL;
            }
            $this->display_payment_slip($order, true);
        }
    }

    /**
     * Output for the order received page.
     *
     * @param int $order_id
     */
    public function thankyou_page($order_id)
    {
        if ($this->options['instructions']) {
            echo wpautop(wptexturize(wp_kses_post($this->options['instructions'])));
        }
        $order = wc_get_order( $order_id );
        $this->display_payment_slip($order, false);
    }
     /**
     * Output for the My account -> View order page.
     */
    public function view_order_instructions($order_id) {
	// Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );
        
        $initial_order_status = get_option("woocommerce_{$this->domain}_settings")['order_status'];
		if ($this->domain === $order->get_payment_method() && $order->has_status(ltrim($initial_order_status, 'wc-'))) {
			if ($this->options['instructions']) {
				echo wpautop(wptexturize(wp_kses_post($this->options['instructions'])));
			}
			$this->display_payment_slip($order, false);
		 }
    }
}
