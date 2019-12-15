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
                    'ADMG' => 'Administracija',
                    'GVEA' => 'Austrijski državni zaposlenici, Kategorija A',
                    'GVEB' => 'Austrijski državni zaposlenici, Kategorija B',
                    'GVEC' => 'Austrijski državni zaposlenici, Kategorija C',
                    'GVED' => 'Austrijski državni zaposlenici, Kategorija D',
                    'BUSB' => 'Autobusni',
                    'CPYR' => 'Autorsko pravo',
                    'HSPC' => 'Bolnička njega',
                    'RDTX' => 'Cestarina',
                    'DEPT' => 'Depozit',
                    'DERI' => 'Derivati (izvedenice)',
                    'FREX' => 'Devizno tržište',
                    'CGDD' => 'Direktno terećenje nastalo kao rezultat kartične transakcije',
                    'DIVD' => 'Dividenda',
                    'BECH' => 'Dječji doplatak',
                    'CHAR' => 'Dobrotvorno plaćanje',
                    'ETUP' => 'Doplata e-novca',
                    'MTUP' => 'Doplata mobilnog uređaja (bon)',
                    'GOVI' => 'Državno osiguranje',
                    'ENRG' => 'Energenti',
                    'CDCD' => 'Gotovinska isplata',
                    'CSDB' => 'Gotovinska isplata',
                    'TCSC' => 'Gradske naknade',
                    'CDCS' => 'Isplata gotovine s naknadom',
                    'FAND' => 'Isplata naknade za elementarne nepogode',
                    'CSLP' => 'Isplata socijalnih zajmova društava  banci',
                    'RHBS' => 'Isplata za vrijeme profesionalne rehabilitacije',
                    'GWLT' => 'Isplata žrtvama rata i invalidima',
                    'ADCS' => 'Isplate za donacije, sponzorstva, savjetodavne, intelektualne i druge usluge',
                    'PADD' => 'Izravno terećenje',
                    'INTE' => 'Kamata',
                    'CDDP' => 'Kartično plaćanje s odgodom',
                    'CDCB' => 'Kartično plaćanje uz gotovinski povrat (Cashback)',
                    'BOCE' => 'Knjiženje konverzije u Back Office-u',
                    'POPE' => 'Knjiženje mjesta kupnje',
                    'RCKE' => 'Knjiženje ponovne prezentacije čeka',
                    'AREN' => 'Knjiženje računa potraživanja',
                    'COMC' => 'Komercijalno plaćanje',
                    'UBIL' => 'Komunalne usluge',
                    'COMT' => 'Konsolidirano plaćanje treće strane za račun potrošača.',
                    'SEPI' => 'Kupnja vrijednosnica (interna)',
                    'GDDS' => 'Kupovina-prodaja roba',
                    'GSCB' => 'Kupovina-prodaja roba i usluga uz gotovinski povrat',
                    'GDSV' => 'Kupovina/prodaja roba i usluga',
                    'SCVE' => 'Kupovina/prodaja usluga',
                    'HLTC' => 'Kućna njega bolesnika',
                    'CBLK' => 'Masovni kliring kartica',
                    'MDCS' => 'Medicinske usluge',
                    'NWCM' => 'Mrežna komunikacija',
                    'RENT' => 'Najam',
                    'ALLW' => 'Naknada',
                    'SSBE' => 'Naknada socijalnog osiguranja',
                    'LICF' => 'Naknada za licencu',
                    'GFRP' => 'Naknada za nezaposlene u toku stečaja',
                    'BENE' => 'Naknada za nezaposlenost/invaliditet',
                    'CFEE' => 'Naknada za poništenje',
                    'AEMP' => 'Naknada za zapošljavanje',
                    'COLL' => 'Naplata',
                    'FCOL' => 'Naplata naknade po kartičnoj transakciji',
                    'DBTC' => 'Naplata putem terećenja',
                    'NOWS' => 'Nenavedeno',
                    'IDCP' => 'Neopozivo plaćanje sa računa debitne kartice',
                    'ICCP' => 'Neopozivo plaćanje sa računa kreditne kartice',
                    'BONU' => 'Novčana nagrada (bonus).',
                    'PAYR' => 'Obračun plaća',
                    'BLDM' => 'Održavanje zgrada',
                    'HEDG' => 'Omeđivanje rizika (Hedging)',
                    'CDOC' => 'Originalno odobrenje',
                    'PPTI' => 'Osiguranje imovine',
                    'LBRI' => 'Osiguranje iz rada',
                    'OTHR' => 'Ostalo',
                    'CLPR' => 'Otplata glavnice kredita za automobil',
                    'HLRP' => 'Otplata stambenog kredita',
                    'LOAR' => 'Otplata zajma',
                    'ALMY' => 'Plaćanje alimentacije',
                    'RCPT' => 'Plaćanje blagajničke potvrde. (ReceiptPayment)',
                    'PRCP' => 'Plaćanje cijene',
                    'SUPP' => 'Plaćanje dobavljača',
                    'CFDI' => 'Plaćanje dospjele glavnice',
                    'GOVT' => 'Plaćanje države',
                    'PENS' => 'Plaćanje mirovine',
                    'DCRD' => 'Plaćanje na račun debitne kartice.',
                    'CCRD' => 'Plaćanje na račun kreditne kartice',
                    'SALA' => 'Plaćanje plaće',
                    'REBT' => 'Plaćanje popusta/rabata',
                    'TAXS' => 'Plaćanje poreza',
                    'VATX' => 'Plaćanje poreza na dodatnu vrijednost',
                    'RINP' => 'Plaćanje rata koje se ponavljaju',
                    'IHRP' => 'Plaćanje rate pri kupnji na otplatu',
                    'IVPT' => 'Plaćanje računa',
                    'CDBL' => 'Plaćanje računa za kreditnu karticu',
                    'TREA' => 'Plaćanje riznice',
                    'CMDT' => 'Plaćanje roba',
                    'INTC' => 'Plaćanje unutar društva',
                    'INVS' => 'Plaćanje za fondove i vrijednosnice',
                    'PRME' => 'Plemeniti metali',
                    'AGRT' => 'Poljoprivredni transfer',
                    'INTX' => 'Porez na dohodak',
                    'PTXP' => 'Porez na imovinu',
                    'NITX' => 'Porez na neto dohodak',
                    'ESTX' => 'Porez na ostavštinu',
                    'GSTX' => 'Porez na robu i usluge',
                    'HSTX' => 'Porez na stambeni prostor',
                    'FWLV' => 'Porez na strane radnike',
                    'WHLD' => 'Porez po odbitku',
                    'BEXP' => 'Poslovni troškovi',
                    'REFU' => 'Povrat',
                    'TAXR' => 'Povrat poreza',
                    'RIMB' => 'Povrat prethodne pogrešne transakcije',
                    'OFEE' => 'Početna naknada (Opening Fee)',
                    'ADVA' => 'Predujam',
                    'INSU' => 'Premija osiguranja',
                    'INPC' => 'Premija osiguranja za vozilo',
                    'TRPT' => 'Prepaid cestarina (ENC)',
                    'SUBS' => 'Pretplata',
                    'CASH' => 'Prijenos gotovine',
                    'PENO' => 'Prisilna naplata',
                    'COMM' => 'Provizija',
                    'INSM' => 'Rata',
                    'ELEC' => 'Račun za električnu energiju',
                    'CBTV' => 'Račun za kabelsku TV',
                    'OTLC' => 'Račun za ostale telekom usluge',
                    'GASB' => 'Račun za plin',
                    'WTER' => 'Račun za vodu',
                    'ANNI' => 'Renta',
                    'BBSC' => 'Rodiljna naknada',
                    'NETT' => 'Saldiranje (netiranje)',
                    'CAFI' => 'Skrbničke naknade (interne)',
                    'STDY' => 'Studiranje',
                    'ROYA' => 'Tantijeme',
                    'PHON' => 'Telefonski račun',
                    'FERB' => 'Trajektni',
                    'DMEQ' => 'Trajna medicinska pomagala',
                    'WEBI' => 'Transakcija inicirana internetom',
                    'TELI' => 'Transakcija inicirana telefonom',
                    'HREC' => 'Transakcija se odnosi na doprinos poslodavca za troškove stanovanja',
                    'CBFR' => 'Transakcija se odnosi na kapitalnu štednju za mirovinu',
                    'CBFF' => 'Transakcija se odnosi na kapitalnu štednju, općenito',
                    'TRAD' => 'Trgovinske usluge',
                    'COST' => 'Troškovi',
                    'CPKC' => 'Troškovi parkiranja',
                    'TBIL' => 'Troškovi telekomunikacija',
                    'NWCH' => 'Troškovi za mrežu',
                    'EDUC' => 'Troškovi školovanja',
                    'LIMA' => 'Upravljanje likvidnošću',
                    'ACCT' => 'Upravljanje računom',
                    'ANTS' => 'Usluge anestezije',
                    'VIEW' => 'Usluge oftalmološke skrbi',
                    'LTCF' => 'Ustanova dugoročne zdravstvene skrbi',
                    'ICRF' => 'Ustanova socijalne skrbi',
                    'CVCF' => 'Ustanova za usluge skrbi za rekonvalescente',
                    'PTSP' => 'Uvjeti plaćanja',
                    'MSVC' => 'Višestruke vrste usluga',
                    'SECU' => 'Vrijednosni papiri',
                    'LOAN' => 'Zajam',
                    'FCPM' => 'Zakašnjele naknade',
                    'TRFD' => 'Zaklada',
                    'CDQC' => 'Zamjenska gotovina',
                    'HLTI' => 'Zdravstveno osiguranje',
                    'AIRB' => 'Zračni',
                    'DNTS' => 'Zubarske usluge',
                    'SAVG' => 'Štednja',
                    'RLWY' => 'Željeznički',
                    'LIFI' => 'Životno osiguranje'
                )
            ),
            'payment_model' => array(
                'title'       => __( 'Payment model', $this->id ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => '',
                'options'     => array(
                    ''     => __( '(undefined)', $this->id ),
                    'HR00' => 'HR00',
                    'HR01' => 'HR01',
                    'HR02' => 'HR02',
                    'HR03' => 'HR03',
                    'HR04' => 'HR04',
                    'HR05' => 'HR05',
                    'HR06' => 'HR06',
                    'HR07' => 'HR07',
                    'HR08' => 'HR08',
                    'HR09' => 'HR09',
                    'HR10' => 'HR10',
                    'HR11' => 'HR11',
                    'HR12' => 'HR12',
                    'HR13' => 'HR13',
                    'HR14' => 'HR14',
                    'HR15' => 'HR15',
                    'HR16' => 'HR16',
                    'HR17' => 'HR17',
                    'HR18' => 'HR18',
                    'HR23' => 'HR23',
                    'HR24' => 'HR24',
                    'HR25' => 'HR25',
                    'HR26' => 'HR26',
                    'HR27' => 'HR27',
                    'HR28' => 'HR28',
                    'HR29' => 'HR29',
                    'HR30' => 'HR30',
                    'HR31' => 'HR31',
                    'HR33' => 'HR33',
                    'HR34' => 'HR34',
                    'HR40' => 'HR40',
                    'HR41' => 'HR41',
                    'HR42' => 'HR42',
                    'HR43' => 'HR43',
                    'HR50' => 'HR50',
                    'HR55' => 'HR55',
                    'HR62' => 'HR62',
                    'HR63' => 'HR63',
                    'HR64' => 'HR64',
                    'HR65' => 'HR65',
                    'HR67' => 'HR67',
                    'HR68' => 'HR68',
                    'HR69' => 'HR69',
                    'HR83' => 'HR83',
                    'HR84' => 'HR84',
                    'HR99' => 'HR99'
                )
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

    public function get_nowrapped_large_text($text) {
        return '<h1>' . str_replace(array(' ', '-'), array('&nbsp;', '−'), $text) . '</h1>';
    }
}