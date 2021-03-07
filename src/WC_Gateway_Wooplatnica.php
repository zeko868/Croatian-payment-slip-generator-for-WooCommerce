<?php

class WC_Gateway_Wooplatnica extends WC_Payment_Gateway
{

    /**
     * WC_Gateway_Wooplatnica constructor.
     */
    public function __construct()
    {
        $this->id = 'wooplatnica-croatia';

        $this->init_settings();
        $this->init_form_fields();

        $this->has_fields         = false;
        $this->method_title       = __( '[CRO] Payment slip or mobile banking', $this->id );
        $this->method_description = __( 'Make it easy for your customers from Croatia to perform direct bank transfer with generated and pre-filled payment slip, along with mobile banking by simple barcode scanning.', $this->id );
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');

        add_action("woocommerce_update_options_payment_gateways_{$this->id}", array($this, 'process_admin_options'));

    }

    /**
     * Initialize gateway settings
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', $this->id ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable payment slip generator for Croatia', $this->id ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', $this->id ),
                'description' => __( 'Payment gateway title on checkout.', $this->id ),
                'type'        => 'text',
                'default'     => __( 'Pay using payment slip or mobile banking', $this->id ),
                'desc_tip'    => true,
            ),
            'order_status' => array(
                'title'       => __( 'Order Status', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Choose whether status you wish after checkout.', $this->id ),
                'default'     => 'wc-on-hold',
                'desc_tip'    => true,
                'options'     => wc_get_order_statuses()
            ),
            'description' => array(
                'title'       => __( 'Description', $this->id ),
                'description' => __( 'Payment gateway description on checkout.', $this->id ),
                'type'        => 'textarea',
                'default'     => __('Pay using generated and pre-filled payment slip or by scanning a barcode through your mobile banking app', $this->id),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __( 'Instructions', $this->id ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->id ),
                'default'     => __( 'Below you can find instructions required to make a payment:', $this->id ),
                'desc_tip'    => true,
            ),
            'payment_slip_type' => array(
                'title'       => __( 'Payment slip type', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'If your bank account is registered outside of Croatia, you should select \'universal\'.', $this->id ) . '<br/><strong>' . __( 'Note: ', $this->id ) . '</strong>' . __( 'Barcode can be added only to the payment slips for national payments.', $this->id ),
                'default'     => 'national',
                'options'     => array(
                    'universal' => __( 'universal', $this->id ),
                    'national'  => __( 'national', $this->id )
                )
            ),
            'currency' => array(
                'title'       => __( 'Currency code', $this->id ),
                'type'        => 'text',
                'description' => __( 'If none specified, the one from the order information will be used', $this->id ),
                'default'     => 'HRK'
            ),
            'recipient_name' => array(
                'title'       => __( 'Recipient name', $this->id ),
                'type'        => 'textarea',
                'default'     => ''
            ),
            'recipient_address' => array(
                'title'       => __( 'Recipient address', $this->id ),
                'type'        => 'text',
                'default'     => ''
            ),
            'recipient_zip_code' => array(
                'title'       => __( 'Recipient postal code', $this->id ),
                'type'        => 'text',
                'default'     => ''
            ),
            'recipient_city' => array(
                'title'       => __( 'Recipient city', $this->id ),
                'type'        => 'text',
                'default'     => ''
            ),
            'recipient_bank_account_id' => array(
                'title'       => __( 'Recipient\'s IBAN', $this->id ),
                'type'        => 'text',
                'default'     => ''
            ),
            'recipient_callout_number' => array(
                'title'       => __( 'Recipient\'s reference number', $this->id ),
                'description' => __( 'Variables such as %order%, %date%, %year%, %month% and %day% can be used.', $this->id ),
                'type'        => 'text',
                'default'     => '%order%'
            ),
            'intent_code' => array(
                'title'       => __( 'Intent code', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => '',
                'options'     => array(
                    ''     => __( '(undefined)', $this->id ),
                    'ADMG' => 'ADMG - Administracija',
                    'GVEA' => 'GVEA - Austrijski državni zaposlenici, Kategorija A',
                    'GVEB' => 'GVEB - Austrijski državni zaposlenici, Kategorija B',
                    'GVEC' => 'GVEC - Austrijski državni zaposlenici, Kategorija C',
                    'GVED' => 'GVED - Austrijski državni zaposlenici, Kategorija D',
                    'BUSB' => 'BUSB - Autobusni',
                    'CPYR' => 'CPYR - Autorsko pravo',
                    'HSPC' => 'HSPC - Bolnička njega',
                    'RDTX' => 'RDTX - Cestarina',
                    'DEPT' => 'DEPT - Depozit',
                    'DERI' => 'DERI - Derivati (izvedenice)',
                    'FREX' => 'FREX - Devizno tržište',
                    'CGDD' => 'CGDD - Direktno terećenje nastalo kao rezultat kartične transakcije',
                    'DIVD' => 'DIVD - Dividenda',
                    'BECH' => 'BECH - Dječji doplatak',
                    'CHAR' => 'CHAR - Dobrotvorno plaćanje',
                    'ETUP' => 'ETUP - Doplata e-novca',
                    'MTUP' => 'MTUP - Doplata mobilnog uređaja (bon)',
                    'GOVI' => 'GOVI - Državno osiguranje',
                    'ENRG' => 'ENRG - Energenti',
                    'CDCD' => 'CDCD - Gotovinska isplata',
                    'CSDB' => 'CSDB - Gotovinska isplata',
                    'TCSC' => 'TCSC - Gradske naknade',
                    'CDCS' => 'CDCS - Isplata gotovine s naknadom',
                    'FAND' => 'FAND - Isplata naknade za elementarne nepogode',
                    'CSLP' => 'CSLP - Isplata socijalnih zajmova društava  banci',
                    'RHBS' => 'RHBS - Isplata za vrijeme profesionalne rehabilitacije',
                    'GWLT' => 'GWLT - Isplata žrtvama rata i invalidima',
                    'ADCS' => 'ADCS - Isplate za donacije, sponzorstva, savjetodavne, intelektualne i druge usluge',
                    'PADD' => 'PADD - Izravno terećenje',
                    'INTE' => 'INTE - Kamata',
                    'CDDP' => 'CDDP - Kartično plaćanje s odgodom',
                    'CDCB' => 'CDCB - Kartično plaćanje uz gotovinski povrat (Cashback)',
                    'BOCE' => 'BOCE - Knjiženje konverzije u Back Office-u',
                    'POPE' => 'POPE - Knjiženje mjesta kupnje',
                    'RCKE' => 'RCKE - Knjiženje ponovne prezentacije čeka',
                    'AREN' => 'AREN - Knjiženje računa potraživanja',
                    'COMC' => 'COMC - Komercijalno plaćanje',
                    'UBIL' => 'UBIL - Komunalne usluge',
                    'COMT' => 'COMT - Konsolidirano plaćanje treće strane za račun potrošača.',
                    'SEPI' => 'SEPI - Kupnja vrijednosnica (interna)',
                    'GDDS' => 'GDDS - Kupovina-prodaja roba',
                    'GSCB' => 'GSCB - Kupovina-prodaja roba i usluga uz gotovinski povrat',
                    'GDSV' => 'GDSV - Kupovina/prodaja roba i usluga',
                    'SCVE' => 'SCVE - Kupovina/prodaja usluga',
                    'HLTC' => 'HLTC - Kućna njega bolesnika',
                    'CBLK' => 'CBLK - Masovni kliring kartica',
                    'MDCS' => 'MDCS - Medicinske usluge',
                    'NWCM' => 'NWCM - Mrežna komunikacija',
                    'RENT' => 'RENT - Najam',
                    'ALLW' => 'ALLW - Naknada',
                    'SSBE' => 'SSBE - Naknada socijalnog osiguranja',
                    'LICF' => 'LICF - Naknada za licencu',
                    'GFRP' => 'GFRP - Naknada za nezaposlene u toku stečaja',
                    'BENE' => 'BENE - Naknada za nezaposlenost/invaliditet',
                    'CFEE' => 'CFEE - Naknada za poništenje',
                    'AEMP' => 'AEMP - Naknada za zapošljavanje',
                    'COLL' => 'COLL - Naplata',
                    'FCOL' => 'FCOL - Naplata naknade po kartičnoj transakciji',
                    'DBTC' => 'DBTC - Naplata putem terećenja',
                    'NOWS' => 'NOWS - Nenavedeno',
                    'IDCP' => 'IDCP - Neopozivo plaćanje sa računa debitne kartice',
                    'ICCP' => 'ICCP - Neopozivo plaćanje sa računa kreditne kartice',
                    'BONU' => 'BONU - Novčana nagrada (bonus).',
                    'PAYR' => 'PAYR - Obračun plaća',
                    'BLDM' => 'BLDM - Održavanje zgrada',
                    'HEDG' => 'HEDG - Omeđivanje rizika (Hedging)',
                    'CDOC' => 'CDOC - Originalno odobrenje',
                    'PPTI' => 'PPTI - Osiguranje imovine',
                    'LBRI' => 'LBRI - Osiguranje iz rada',
                    'OTHR' => 'OTHR - Ostalo',
                    'CLPR' => 'CLPR - Otplata glavnice kredita za automobil',
                    'HLRP' => 'HLRP - Otplata stambenog kredita',
                    'LOAR' => 'LOAR - Otplata zajma',
                    'ALMY' => 'ALMY - Plaćanje alimentacije',
                    'RCPT' => 'RCPT - Plaćanje blagajničke potvrde. (ReceiptPayment)',
                    'PRCP' => 'PRCP - Plaćanje cijene',
                    'SUPP' => 'SUPP - Plaćanje dobavljača',
                    'CFDI' => 'CFDI - Plaćanje dospjele glavnice',
                    'GOVT' => 'GOVT - Plaćanje države',
                    'PENS' => 'PENS - Plaćanje mirovine',
                    'DCRD' => 'DCRD - Plaćanje na račun debitne kartice.',
                    'CCRD' => 'CCRD - Plaćanje na račun kreditne kartice',
                    'SALA' => 'SALA - Plaćanje plaće',
                    'REBT' => 'REBT - Plaćanje popusta/rabata',
                    'TAXS' => 'TAXS - Plaćanje poreza',
                    'VATX' => 'VATX - Plaćanje poreza na dodatnu vrijednost',
                    'RINP' => 'RINP - Plaćanje rata koje se ponavljaju',
                    'IHRP' => 'IHRP - Plaćanje rate pri kupnji na otplatu',
                    'IVPT' => 'IVPT - Plaćanje računa',
                    'CDBL' => 'CDBL - Plaćanje računa za kreditnu karticu',
                    'TREA' => 'TREA - Plaćanje riznice',
                    'CMDT' => 'CMDT - Plaćanje roba',
                    'INTC' => 'INTC - Plaćanje unutar društva',
                    'INVS' => 'INVS - Plaćanje za fondove i vrijednosnice',
                    'PRME' => 'PRME - Plemeniti metali',
                    'AGRT' => 'AGRT - Poljoprivredni transfer',
                    'INTX' => 'INTX - Porez na dohodak',
                    'PTXP' => 'PTXP - Porez na imovinu',
                    'NITX' => 'NITX - Porez na neto dohodak',
                    'ESTX' => 'ESTX - Porez na ostavštinu',
                    'GSTX' => 'GSTX - Porez na robu i usluge',
                    'HSTX' => 'HSTX - Porez na stambeni prostor',
                    'FWLV' => 'FWLV - Porez na strane radnike',
                    'WHLD' => 'WHLD - Porez po odbitku',
                    'BEXP' => 'BEXP - Poslovni troškovi',
                    'REFU' => 'REFU - Povrat',
                    'TAXR' => 'TAXR - Povrat poreza',
                    'RIMB' => 'RIMB - Povrat prethodne pogrešne transakcije',
                    'OFEE' => 'OFEE - Početna naknada (Opening Fee)',
                    'ADVA' => 'ADVA - Predujam',
                    'INSU' => 'INSU - Premija osiguranja',
                    'INPC' => 'INPC - Premija osiguranja za vozilo',
                    'TRPT' => 'TRPT - Prepaid cestarina (ENC)',
                    'SUBS' => 'SUBS - Pretplata',
                    'CASH' => 'CASH - Prijenos gotovine',
                    'PENO' => 'PENO - Prisilna naplata',
                    'COMM' => 'COMM - Provizija',
                    'INSM' => 'INSM - Rata',
                    'ELEC' => 'ELEC - Račun za električnu energiju',
                    'CBTV' => 'CBTV - Račun za kabelsku TV',
                    'OTLC' => 'OTLC - Račun za ostale telekom usluge',
                    'GASB' => 'GASB - Račun za plin',
                    'WTER' => 'WTER - Račun za vodu',
                    'ANNI' => 'ANNI - Renta',
                    'BBSC' => 'BBSC - Rodiljna naknada',
                    'NETT' => 'NETT - Saldiranje (netiranje)',
                    'CAFI' => 'CAFI - Skrbničke naknade (interne)',
                    'STDY' => 'STDY - Studiranje',
                    'ROYA' => 'ROYA - Tantijeme',
                    'PHON' => 'PHON - Telefonski račun',
                    'FERB' => 'FERB - Trajektni',
                    'DMEQ' => 'DMEQ - Trajna medicinska pomagala',
                    'WEBI' => 'WEBI - Transakcija inicirana internetom',
                    'TELI' => 'TELI - Transakcija inicirana telefonom',
                    'HREC' => 'HREC - Transakcija se odnosi na doprinos poslodavca za troškove stanovanja',
                    'CBFR' => 'CBFR - Transakcija se odnosi na kapitalnu štednju za mirovinu',
                    'CBFF' => 'CBFF - Transakcija se odnosi na kapitalnu štednju, općenito',
                    'TRAD' => 'TRAD - Trgovinske usluge',
                    'COST' => 'COST - Troškovi',
                    'CPKC' => 'CPKC - Troškovi parkiranja',
                    'TBIL' => 'TBIL - Troškovi telekomunikacija',
                    'NWCH' => 'NWCH - Troškovi za mrežu',
                    'EDUC' => 'EDUC - Troškovi školovanja',
                    'LIMA' => 'LIMA - Upravljanje likvidnošću',
                    'ACCT' => 'ACCT - Upravljanje računom',
                    'ANTS' => 'ANTS - Usluge anestezije',
                    'VIEW' => 'VIEW - Usluge oftalmološke skrbi',
                    'LTCF' => 'LTCF - Ustanova dugoročne zdravstvene skrbi',
                    'ICRF' => 'ICRF - Ustanova socijalne skrbi',
                    'CVCF' => 'CVCF - Ustanova za usluge skrbi za rekonvalescente',
                    'PTSP' => 'PTSP - Uvjeti plaćanja',
                    'MSVC' => 'MSVC - Višestruke vrste usluga',
                    'SECU' => 'SECU - Vrijednosni papiri',
                    'LOAN' => 'LOAN - Zajam',
                    'FCPM' => 'FCPM - Zakašnjele naknade',
                    'TRFD' => 'TRFD - Zaklada',
                    'CDQC' => 'CDQC - Zamjenska gotovina',
                    'HLTI' => 'HLTI - Zdravstveno osiguranje',
                    'AIRB' => 'AIRB - Zračni',
                    'DNTS' => 'DNTS - Zubarske usluge',
                    'SAVG' => 'SAVG - Štednja',
                    'RLWY' => 'RLWY - Željeznički',
                    'LIFI' => 'LIFI - Životno osiguranje'
                )
            ),
            'payment_model' => array(
                'title'       => __( 'Payment model', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => '',
                'options'     => $this->generate_payment_models()
            ),
            'payment_description' => array(
                'title'       => __( 'Payment description', $this->id ),
                'type'        => 'textarea',
                'description' => __( 'Variables such as %order%, %date%, %year%, %month% and %day% can be used.', $this->id ),
                'default'     => ''
            ),
            '!universal-payment-header' => array(     // dirty hack for header
                'title'     => $this->get_nowrapped_large_text(__( 'Options for universal payment slip type', $this->id )),
                'type'      => 'text',
                'css'       => 'display: none;'
            ),
            'recipient_swift_code' => array(
                'title'     => __( 'Recipient SWIFT/BIC code', $this->id ),
                'type'      => 'text'
            ),
            'recipient_bank_name' => array(
                'title'     => __( 'Recipient bank name', $this->id ),
                'type'      => 'textarea'
            ),
            'recipient_person_type' => array(
                'title'     => __( 'Recipient person type', $this->id ),
                'type'      => 'select',
                'class'     => 'wc-enhanced-select',
                'options'   => array(
                    ''          => __( '(undefined)', $this->id ),
                    'natural'   => __( 'natural', $this->id ),
                    'legal'     => __( 'legal', $this->id ),
                ),
                'default'   => ''
            ),
            'sepa_transfer_currency' => array(
                'title'         => __( 'SEPA transfer currency code', $this->id ),
                'type'          => 'text'
            ),
            'swift_charge_option' => array(
                'title'       => __( 'SWIFT charge option', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'options'     => array(
                    ''      => __( '(undefined)', $this->id ),
                    'BEN'   => 'BEN',
                    'SHA'   => 'SHA',
                    'OUR'   => 'OUR'
                ),
                'description' => __( 'Which side will have to pay payment fee/costs. BEN for other side, OUR for us or SHA for cost sharing', $this->id ),
                'default'     => '',
                'desc_tip'    => true
            ),
            '!national-payment-header' => array(     // dirty hack for header
                'title'     => $this->get_nowrapped_large_text(__( 'Options for national payment slip type', $this->id )),
                'type'      => 'text',
                'css'       => 'display: none;'
            ),
            'display_barcode' => array(
                'title'         => __( 'Barcode for mobile banking', $this->id ),
                'type'          => 'checkbox',
                'label'         => __( 'Generate barcode in PDF417 format', $this->id ),
                'description'   => __('Barcode can be added only to the payment slips for national payments.', $this->id ),
                'default'       => 'yes'
            ),
            '!other' => array(     // dirty hack for header
                'title'     => $this->get_nowrapped_large_text(__( 'Other options', $this->id )),
                'type'      => 'text',
                'css'       => 'display: none;'
            ),
            'output_image_type' => array(
                'title'         => __( 'Output image type', $this->id ),
                'description'   => __( 'Type of the image with the generated payment slip', $this->id ),
                'type'          => 'select',
                'class'         => 'wc-enhanced-select',
                'default'       => 'png',
                'options'       => array_filter(array(
                    'png'   => 'png',
                    'jpeg'  => 'jpeg',
                    'gif'   => 'gif',
                    'bmp'   => 'bmp'
                ), function ($image_type) {
                    return function_exists('image' . $image_type);
                }, ARRAY_FILTER_USE_KEY),
                'desc_tip'		=> true
            ),
            'payment_slip_email_width' => array(
				'title'			=> __( 'Width of the inline image in e-mail message', $this->id ),
				'description'	=> __( 'Height is being scaled to width proportionally. Leave empty if you don\'t want to specify dimensions of inline image. Not recommended since in some e-mail clients like Windows 10 Mail UWP app the image would be shown in its original size instead of maximum available space thus often causing the need for using horizontal slider in order to see complete content of the e-mail message.', $this->id ),
				'type'			=> 'text',
				'css'			=> 'width: 3em;',
				'default'		=> '640',
				'desc_tip'		=> true
			),
            'display_confirmation_part' => array(
                'title'       => __( 'Display confirmation part', $this->id ),
                'type'        => 'checkbox',
                'label'       => __( 'Display confirmation part of the payment slip', $this->id ),
                'default'     => 'yes'
            ),
            'main_font' => array(
                'title'       => __( 'Main font', $this->id ),
                'description' => __( 'Font being used for the text that won\'t be displayed in fields with cells for each character', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'proportional',
                'options'     => array(
                    'proportional'  => __( 'proportional', $this->id ),
                    'monospaced'    => __( 'monospaced', $this->id )
                ),
                'desc_tip'    => true
            ),
        );
    }

    /**
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

        $order_status = get_option("woocommerce_{$this->id}_settings")['order_status'];
        $order->update_status($order_status, __('Awaiting payment', 'woocommerce'));
        //$order->wc_reduce_stock_levels();
        $woocommerce->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    private function get_nowrapped_large_text($text) {
        return '<h1>' . str_replace(array(' ', '-'), array('&nbsp;', '−'), $text) . '</h1>';
    }
    
    private function generate_payment_models() {
        $models = array_map(function($num) {
            $num = str_pad($num, 2, '0', STR_PAD_LEFT);
            return "HR$num";
        }, range(0, 99));
        return array_merge(
            array(
                ''  => __( '(undefined)', $this->id )
            ),
            array_combine($models, $models)
        );
    }
}
