<p align="center"><a href="https://wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/"><img src="https://raw.githubusercontent.com/marlevak/Croatian-payment-slip-generator-for-WooCommerce/master/images/banner-1544x500.png" alt="Banner WooCommerce modula za plaćanje putem opće uplatnice ili mobilnim bankarstvom"></a></p>

*Read this in other languages: [English](README.md), [Hrvatski](README.hr.md).*

## Opis

Olakšajte svojim korisnicima kupovinu tako da im omogućite plaćanje općom uplatnicom i mobilnim bankarstvom.

Ovaj dodatak dodaje još jedan način plaćanja u WooCommerce sustav koji je zapravo prilagodba predinstaliranog načina plaćanja “Direktna bankovna transakcija”, a prikladan je za korisnike iz Republike Hrvatske koji u velikoj mjeri koriste ovaj način za provedbu transakcija.
Instalacijom, aktivacijom i uključivanjem ovog načina plaćanja, korisnici Vaše web-stranice će biti u mogućnosti izvršiti plaćanje na neki od sljedećih načina:

* općom uplatnicom čija su polja automatski popunjena s podacima o primatelju, platitelju i izvršenoj narudžbi te koju je moguće preuzeti i ispisati radi provedbe plaćanja u bilo kojoj banci ili pošti
* skeniranjem barkoda (koji se nalazi na spomenutoj uplatnici) kroz neku od mobilnih aplikacija hrvatskih banaka čime je za korisnika znatno ubrzan i olakšan proces plaćanja

**Dostupni prijevodi:**

* hrvatski
* engleski


## Zahtjevi
Kako bi ovaj dodatak normalno radio, sljedeći PHP moduli moraju biti instalirani i uključeni:
* bcmath
* fileinfo
* gd
* mbstring


## Instalacija

Ovo poglavlje opisuje kako instalirati ovaj modul te ga osposobiti za korištenje.


1. Instalirajte ovaj plugin kroz upravljač dodataka iz WordPress administratorske upravljačke ploče koristeći tražilicu dodataka i instalacijom pronađenog rezultata ili pak prenesite [programski kôd](https://github.com/marlevak/Croatian-payment-slip-generator-for-WooCommerce/releases/latest) ovog plugina (ili izvorni kôd repozitorija zajedno sa svim zavisnim modulima pozivom `composer install` naredbe) u `/wp-content/plugins/` direktorij
2. Aktivirajte ovaj plugin kroz izbornik 'Dodaci' u WordPress administratorskoj upravljačkoj ploči


## Slike zaslona

Novi način plaćanja se prikazuje na stranici za plaćanje te ga je tako ujedno moguće i odabrati<br/>
![opcije plaćanja na stranici za plaćanje](/images/screenshot-1.png)<br/>
Nakon što korisnik odabere ovaj način plaćanja te nastavi dalje na plaćanje, prikazuje mu se popunjena opća uplatnica - kako s podacima o platitelju, tako i o njemu samome i o samoj narudžbi. Slika te iste uplatnice se ujedno šalje i na korisnikovu e-mail adresu<br/>
![prikaz opće uplatnice s barkodom](/images/screenshot-2.png)<br/>
Sva polja opće uplatnice (kao i razne druge postavke modula) se mogu podesiti na stranici za upravljanje/postavke ovog načina plaćanja, a koji se nalazi unutar WooCommerce upravljačke ploče
![mogućnosti konfiguracije opcije plaćanja ovog dodatka 1. dio](/images/screenshot-3.png)<br/>
![mogućnosti konfiguracije opcije plaćanja ovog dodatka 2. dio](/images/screenshot-4.png)<br/>


## Najčešće postavljena pitanja

**Q:** Gdje se mogu prijaviti uočene pogreške u radu ili kako je pak moguće pridonijeti ovom projektu?

**A:** Pogreške i prijedloge možete objaviti u [issue sekciji](https://github.com/marlevak/croatian-payment-slip-generator-for-woocommerce/issues) GitHub repozitorija ovog plugina, kao i na forumu za podršku [ovdje na WordPressu](https://wordpress.org/support/plugin/croatian-payment-slip-generator-for-woocommerce).
___
**Q:** Kako izvršiti pretvorbu cijene narudžbe iz originalne valute narudžbe u onu koja se navodi na općoj uplatnici?

**A:** Unutar `functions.php` datoteke je potrebno dodati funkciju na filter 'wooplatnica-croatia_order' koja bi vršila željenu konverziju izvorne cijene narudžbe (pri čemu se iznos cijene može izvući iz objekta klase WC_Order kojeg spomenuta funkcija prima kao prvi/jedini argument) te bi kao rezultat varila instancu klase WC_Order s ažuriranim vrijednostima polja.

## Posebne zahvale

* [Webmonster](https://webmonster.rs/) - razvojni tim koji je razvio WordPress dodatak naziva [Wooplatnica (Serbian payment slip generator for WooCommerce)](https://wordpress.org/plugins/wooplatnica/)
* [Ivan Habunek](https://github.com/ihabunek) - autor [generatora barkoda specifikacije PDF 417 za programski jezik PHP](https://github.com/ihabunek/pdf417-php)

## Vanjske poveznice
* [Stranica plugina na WordPressu](https://hr.wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/)
* [Stranica plugina na WordPressu s dokumentacijom na engleskom jeziku](https://wordpress.org/plugins/croatian-payment-slip-generator-for-woocommerce/)
