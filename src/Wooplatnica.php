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
            add_action( 'wp_enqueue_scripts', array($this, 'callback_for_setting_up_scripts'));
        }
    }

    public function callback_for_setting_up_scripts() {
        $plugin_directory = dirname(plugin_dir_url(__FILE__));
        wp_register_style( 'bootstrap-dropdown-button', $plugin_directory . '/assets/css/bootstrap-dropdown-button/bootstrap.css' );
        wp_register_script( 'popper-js', $plugin_directory . '/assets/js/Popper.js/popper.min.js', array(), false, true);
        wp_register_script( 'polyfills', $plugin_directory . '/assets/js/polyfills/polyfills.umd.min.js', array(), false, true);
        wp_register_script( 'jspdf', $plugin_directory . '/assets/js/jsPDF/jspdf.umd.min.js', array('polyfills'), false, true);
        wp_register_script( 'bootstrap-dropdown-button', $plugin_directory . '/assets/js/bootstrap-dropdown-button/bootstrap.min.js', array( 'jquery', 'popper-js' ), false, true );
    }

    private function display_payment_slip($order, $is_for_sending) {
        $default_options = array(   // add here default values of options that were introduced after initial version (a.k.a. version 1.0) of this plugin
            'display_confirmation_part' => 'yes',
            'payment_slip_type'         => 'national',
            'main_font'                 => 'proportional',
            'output_image_type'         => 'png',
            'payment_slip_email_width'  => '640',
            'payment_slip_files_email'  => array('image-normal'),
            'payment_slip_files_website'=> array('pdf-print')
        );
        $this->options = array_merge($default_options, $this->options); // this is useful because after updating plugin, options that didn't exist in previous version of plugin are not yet stored in the database, i.e. when those options would be fetched, their values would be null even if those newly defined options have defined default values in WC_Gateway_Wooplatnica.php, what resulted in unexcepted aad buggy behavior

        session_start();
        $order_id = $order->get_id();
        $payment_slip_image_session_key_prefix = 'payment-slip-image-';
        $current_payment_slip_image_session_key = $payment_slip_image_session_key_prefix . $order_id;
        $image_type = $this->options['output_image_type'];

        if (isset($_SESSION[$current_payment_slip_image_session_key])) {
            $payment_slip_blob = $_SESSION[$current_payment_slip_image_session_key];
            unset($_SESSION[$current_payment_slip_image_session_key]);
        }
        else {
            $session_keys_of_previous_images = array_filter(array_keys($_SESSION), function($key) use ($payment_slip_image_session_key_prefix) {
                return strpos($key, $payment_slip_image_session_key_prefix) === 0;
            });
            foreach ($session_keys_of_previous_images as $key) {
                unset($_SESSION[$key]);
            }
            $im = $this->generate_payment_slip($order);
            $payment_slip_blob = $this->get_image_blob_from_image_resource($im, $image_type);
        }
        
        $order_number = $order->get_order_number();
        $webapp_name = get_bloginfo('name');
        $webapp_name_for_filename = mb_ereg_replace("([\.]{2,})", '', mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $webapp_name));
        $file_name = sprintf( '%s-%s-%s', __('payment-slip', $this->domain), $webapp_name_for_filename, $order_number);
        $print_version_file_name_suffix = __('print', $this->domain);
        $display_confirmation_part = $this->options['display_confirmation_part'] === 'yes';
        $payment_slip_width = $display_confirmation_part ? 1580 : 1125;
        $image_cropping_dimensions = ['x' => 60, 'y' => 823, 'width' => $payment_slip_width, 'height' => 620];
        $location_for_cropped_image_on_pdf = ['x' => 10, 'y' => 10, 'width' => 0, 'height' => 75];  // width 0 means that the width is being calculated proportionally based on specified height
        if ($is_for_sending) {
            if (in_array('image-normal', $this->options['payment_slip_files_email']) || in_array('pdf-normal', $this->options['payment_slip_files_email'])) {
                $cropped_im = imagecrop($im, $image_cropping_dimensions);
                $cropped_payment_slip_blob = $this->get_image_blob_from_image_resource($cropped_im, $image_type);
                unset($cropped_im);
            }

            $img_element_alt = __('Problem loading image of payment slip', $this->domain);
			if (!empty($this->options['payment_slip_email_width'])) {
				$width_attribute = "width=\"{$this->options['payment_slip_email_width']}\"";
			}
			else {
				$width_attribute = '';
			}

            foreach ($this->options['payment_slip_files_email'] as $preferred_type) {
                if (strrpos($preferred_type, '-normal') === false) {
                    $current_payment_slip_blob = &$payment_slip_blob;
                    $actual_file_name = $file_name;
                }
                else {
                    $current_payment_slip_blob = &$cropped_payment_slip_blob;
                    $actual_file_name = "$file_name-$print_version_file_name_suffix";
                }
                if (strpos($preferred_type, 'image-') === 0) {
                    $image_identifier = "payment-slip-$preferred_type";
                    echo "<img src=\"cid:$image_identifier\" alt=\"$img_element_alt\" $width_attribute/>";
                    add_action( 'phpmailer_init', function() use ($current_payment_slip_blob, $image_identifier, $actual_file_name, $image_type) {
                        global $phpmailer;
                        $phpmailer->SMTPKeepAlive = true;
                        $phpmailer->AddStringEmbeddedImage($current_payment_slip_blob, $image_identifier, "$actual_file_name.$image_type", 'base64', "image/$image_type");
                    });
                }
                else {
                    if ($image_type === 'bmp') { // FPDF library does not support importing BMP images into PDF documents, so image is converted to PNG format as the format is widely supported (if not even more) and the image quality remains the same since it is format using lossless compression
                        $current_payment_slip_blob = $this->get_image_blob_from_image_resource(imagecreatefromstring($current_payment_slip_blob), 'png');
                        $actual_image_type = 'png';
                    }
                    else {
                        $actual_image_type = $image_type;
                    }
                    $pdf = new FPDF();
                    $pic = 'data://text/plain;base64,' . base64_encode($current_payment_slip_blob);
                    $pdf->AddPage();
                    if ($preferred_type === 'pdf-print') {
                        $pdf->Image($pic, 0, 0, 210, 297, $actual_image_type);  // 210x297 are dimenions of A4 paper in millimeters
                    }
                    else {
                        $pdf->Image($pic, $location_for_cropped_image_on_pdf['x'], $location_for_cropped_image_on_pdf['y'], $location_for_cropped_image_on_pdf['width'], $location_for_cropped_image_on_pdf['height'], $actual_image_type);
                    }
                    $payment_slip_pdf_blob = $pdf->Output('S');
                    add_action( 'phpmailer_init', function() use ($payment_slip_pdf_blob, $actual_file_name) {
                        global $phpmailer;
                        $phpmailer->SMTPKeepAlive = true;
                        $phpmailer->AddStringAttachment($payment_slip_pdf_blob, "$actual_file_name.pdf");
                    });
                }
            }
            $_SESSION[$current_payment_slip_image_session_key] = $payment_slip_blob;
        }
        else {
            $img_element_alt = __('Payment slip image', $this->domain);

            if (empty($this->options['payment_slip_files_website'])) {
                $dropdown_button_code = '';
            }
            else {
                $download_button_text = __('Download payment slip', $this->domain);

                $download_option_names = array(
                    'normal' => __('normal version', $this->domain),
                    'print' => __('print version', $this->domain)
                );

                $download_category_names = array(
                    'pdf' => __('as PDF', $this->domain),
                    'image' => __('as image', $this->domain)
                );
                
                $dropdown_button_code = '<button class="btn btn-default dropdown-toggle" type="button" id="download-payment-slip" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">';
                $dropdown_button_code .= $download_button_text;
                $dropdown_required = count($this->options['payment_slip_files_website']) > 1;
                if ($dropdown_required) {
                    wp_enqueue_style( 'bootstrap-dropdown-button' );
                    wp_enqueue_script( 'bootstrap-dropdown-button' );
                    $dropdown_button_code .= '<span class="caret" style="margin-left: 5px"></span>';
                }
                $dropdown_button_code .= '</button>';

                $preffered_types_per_categories = array();
                foreach ($this->options['payment_slip_files_website'] as $preffered_type) {
                    list($category, $type) = explode('-', $preffered_type, 2);
                    $preffered_types_per_categories[$category][$type] = $preffered_type;
                }

                if (array_key_exists('pdf', $preffered_types_per_categories)) {
                    wp_enqueue_script( 'jspdf' );
                }

                $grouping_required = count($preffered_types_per_categories) > 1 && !empty(array_filter(array_values($preffered_types_per_categories), function ($types) {
                    return count($types) > 1;
                }));

                $dropdown_style = $dropdown_required ? '' : 'display: none';

                $dropdown_button_code .= "<ul class=\"dropdown-menu\" aria-labelledby=\"download-payment-slip\" style=\"$dropdown_style\">";
                $first_elem = true;
                foreach ($preffered_types_per_categories as $category => $types) {
                    if ($grouping_required && !$first_elem) {
                        $dropdown_button_code .= '<li role="separator" class="divider"></li>';
                    }
                    foreach ($types as $type => $full_type) {
                        if (count($types) === 1) {
                            $download_type_name = $download_category_names[$category];
                        }
                        else {
                            $download_type_name = "$download_category_names[$category] ($download_option_names[$type])";
                        }
                        $dropdown_button_code .= "<li><a data-id='$full_type'>$download_type_name</a></li>";
                    }
                    $first_elem = false;
                }
                $dropdown_button_code .= '</ul>';
            }

            if ($display_confirmation_part) {
                $payment_slip_image_crop_right_length = '7.5%';
            }
            else {
                $payment_slip_image_crop_right_length = '34%';
            }
            $image_cropping_dimensions_json = json_encode($image_cropping_dimensions);
            $location_for_cropped_image_on_pdf_json = json_encode($location_for_cropped_image_on_pdf);

            $payment_slip_image_data_uri = "data:image/$image_type;base64," . base64_encode($payment_slip_blob);
            echo <<< EOS
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
			</script>
            <div id="payment-slip">
                <div id="payment-slip-image" style="overflow: hidden">
                    <div style="height: 100%">
                        <div>
                            <img src="$payment_slip_image_data_uri" alt="$img_element_alt" onload="cropPaymentSlipImage(this)"/>
                        </div>
                    </div>
                </div>
                <div class="dropdown" style="margin-top: 5px">
                    $dropdown_button_code
                </div>
            </div>
            <script type="text/javascript">
                var fileName = '$file_name';
                var imageType = '$image_type';
                var imageCroppingDimensions = JSON.parse('$image_cropping_dimensions_json');
                var locationForCroppedImageOnPdf = JSON.parse('$location_for_cropped_image_on_pdf_json');

                jQuery("button#download-payment-slip").click(function() {
                    var options = jQuery('button#download-payment-slip + ul.dropdown-menu > li > a');
                    if (options.length === 1) {
                        var selectedOption = options.attr('data-id');
                        downloadPaymentSlip(selectedOption);
                    }
                });

                jQuery("button#download-payment-slip + ul.dropdown-menu > li > a").click(function() {
                    var selectedOption = this.getAttribute('data-id');
                    downloadPaymentSlip(selectedOption);
                });

                function downloadPaymentSlip(selectedOption) {
                    switch (selectedOption) {
                        case 'pdf-print':
                            downloadPrintVersionAsPdf();
                            break;
                        case 'pdf-normal':
                            downloadNormalVersionAsPdf();
                            break;
                        case 'image-print':
                            downloadPrintVersionAsImage();
                            break;
                        case 'image-normal':
                            downloadNormalVersionAsImage();
                            break;
                    }
                }

                function downloadPrintVersionAsPdf() {
                    var fullName = fileName + '-$print_version_file_name_suffix.pdf';

                    //var imageData = jQuery("#payment-slip-image img").prop("src");    // this would usually be sufficient, but causes problems with images inside of PDF documents are of GIF or BMP format (they either have green background instead of transparent/white background or they are über stretched)

                    var canvas = document.createElement("canvas");
                    var imgElement = jQuery("#payment-slip-image img")[0];
                    var height = imgElement.naturalHeight;
                    var width = imgElement.naturalWidth;
                    canvas.height = height;
                    canvas.width = width;
                    canvas.getContext("2d").drawImage(imgElement, 0, 0, width, height, 0, 0, width, height);
                    
                    var imageData = canvas.toDataURL();
                    var doc = new jspdf.jsPDF("p", "mm", "a4");
                    
                    doc.addImage(imageData, imageType, 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight(), "", "FAST");
                    doc.save(fullName);
                }

                function downloadNormalVersionAsPdf() {
                    var fullName = fileName + '.pdf';
                    var imageData = clearUrl(cropImage(jQuery("#payment-slip-image img")[0], imageCroppingDimensions).toDataURL());
                    var doc = new jspdf.jsPDF("p", "mm", "a4");

                    doc.addImage(imageData, imageType, locationForCroppedImageOnPdf.x, locationForCroppedImageOnPdf.y, locationForCroppedImageOnPdf.width, locationForCroppedImageOnPdf.height, "", "FAST");
                    doc.save(fullName);
                }

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

                function downloadPrintVersionAsImage() {
                    downloadImage(jQuery("#payment-slip-image img").prop("src"), '-$print_version_file_name_suffix');
                }

                function downloadNormalVersionAsImage() {
                    downloadImage(cropImage(jQuery("#payment-slip-image img")[0], imageCroppingDimensions).toDataURL(), '');
                }

                function downloadImage(dataUri, fileNameSuffix) {
                    var imageData = clearUrl(dataUri);
                    var fullName = fileName + fileNameSuffix + '.' + imageType;
                    if (navigator.msSaveBlob) {     // Internet Explorer 10+
                        var contentType = 'image/' + imageType;
                        navigator.msSaveBlob(convertBase64StringToBlob(imageData, contentType), fullName); 
                    }
                    else {
                        jQuery("<a/>", {
                            "href": "data:application/octet-stream;base64," + encodeURIComponent(imageData),
                            "download": fullName
                        })[0].click();
                    }
                }

                function cropImage(imageElement, imageCroppingDimensions) {
                    var width = imageCroppingDimensions.width;
                    var height = imageCroppingDimensions.height;
                    var canvas = document.createElement("canvas");
                    canvas.height = height;
                    canvas.width = width;
                    canvas.getContext("2d").drawImage(imageElement, imageCroppingDimensions.x, imageCroppingDimensions.y, width, height, 0, 0, width, height);
                    return canvas;
                }
            </script>
EOS;
        }
    }

    private function get_image_blob_from_image_resource($im, $image_type) {
        ob_start(); // Let's start output buffering.
        $image_saving_method = 'image' . $image_type;
        call_user_func($image_saving_method, $im); //This would normally output the image, but because of ob_start(), it won't.
        $image_blob = ob_get_contents(); //Instead, output above is saved to $contents
        ob_end_clean(); //End the output buffer.
        return $image_blob;
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
        $im = imagecreatefrompng(dirname(__DIR__) . "/assets/images/$input_payment_slip_image_name");

        $proportional_font = dirname(__DIR__) . '/assets/fonts/times-new-roman.ttf';
        $monospaced_font = dirname(__DIR__) . '/assets/fonts/RobotoMono-Regular.ttf';
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
        $this->display_monospace_text_with_specific_spacing($im, 785, 892, $black_color, $monospaced_font, str_pad('=' . str_replace('.', '', $payment_slip_data->get_price()), 15, ' ', STR_PAD_LEFT));
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
        return $im;
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
            if (!(isset($this->options['payment_slip_files_email']) && empty($this->options['payment_slip_files_email']))) {
                if ($this->options['instructions']) {
                    echo wpautop(wptexturize($this->options['instructions'])).PHP_EOL;
                }
                $this->display_payment_slip($order, true);
            }
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
