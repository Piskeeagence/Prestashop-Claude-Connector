# Piskee dla PrestaShop, dokumentacja

Piskee łączy Państwa sklep PrestaShop z Claude: asystentem AI panelu administracyjnego. Wystarczy powiedzieć, co ma zostać zrobione („Proszę przepisać meta opisy kategorii Sukienki”, „Proszę utworzyć rozmiary od S do XL dla tego produktu”, „Których produktów nie ma w magazynie?”), a Claude działa bezpośrednio w Państwa sklepie, z Państwa uprawnieniami i pod Państwa kontrolą.

Zaprojektowany i zbudowany w Chantilly (Francja) przez Piskee, agencję SEO i GEO specjalizującą się w e-commerce.

## Przed rozpoczęciem

- PrestaShop 1.7.6 lub nowszy (1.7, 8 i 9), PHP 7.2 lub nowszy.
- Sklep działający na HTTPS, w katalogu głównym domeny, z włączonymi przyjaznymi adresami URL.
- Nic więcej: moduł zawiera własny serwer MCP i własny serwer autoryzacji OAuth 2.1.
- Konto Claude (claude.ai, aplikacja Claude lub Claude Code).

## Instalacja

1. Moduły, Menedżer modułów, Prześlij moduł: należy przeciągnąć plik zip modułu.
2. Następnie prosimy otworzyć moduł Piskee: zakładka Połączenie sprawdza wszystko. Przycisk **Test połączenia** wykonuje test publicznych adresów na żywo.

## Połączenie z Claude

1. Prosimy skopiować adres widoczny w zakładce Połączenie (kończy się na `/module/piskeeconnector/mcp`).
2. W Claude: Ustawienia, Łączniki, Dodaj, Dodaj własny łącznik.
3. Łącznik należy nazwać „Piskee”, wkleić adres, zostawić wykryte ustawienia (logowanie teraz, automatyczna rejestracja), kliknąć Dodaj, a potem Połącz.
4. Panel administracyjny sklepu otworzy się na ekranie autoryzacji: prosimy sprawdzić wymagane uprawnienia i kliknąć **Zezwól**.

W Claude Code wystarczy jedno polecenie:

```
claude mcp add --transport http piskee https://adres-sklepu.pl/module/piskeeconnector/mcp
```

Następnie należy wpisać `/mcp` w Claude Code, aby się zalogować.

## Co potrafi Claude

**Produkty i kategorie**: wyszukiwanie, odczyt i edycja produktu (ceny, indeksy, EAN, waga, status, teksty i SEO we wszystkich językach), tworzenie produktu (domyślnie nieaktywnego), zarządzanie stanem magazynowym przez podanie dokładnej wartości lub przyjęć i wydań, przypisywanie kategorii, edycja kategorii.

**Kategorie i menu**: tworzenie kategorii w dowolnym miejscu drzewa (nieaktywnej do Państwa akceptacji, widocznej dla tych samych klientów co kategoria nadrzędna), przenoszenie jej, zmiana kolejności wśród kategorii siostrzanych oraz odczyt i edycja menu głównego sklepu (natywny moduł „Menu główne” motywów Classic, Hummingbird i większości innych): dodawanie, usuwanie i zmiana kolejności kategorii, stron CMS, marek i własnych linków. Kategoria utworzona pod kategorią z menu pojawia się w nim automatycznie. Zewnętrzne mega menu są wyświetlane, ale nie są zmieniane.

**Wygląd sklepu bez kodu**: kod opublikowanego motywu nigdy nie jest zmieniany, ale jego ustawienia tak: slider strony głównej (dodawanie, edycja, zmiana kolejności lub wyłączanie slajdów, szybkość), baner, blok tekstu, bloki produktów strony głównej (ile i z której kategorii), linki do mediów społecznościowych oraz które bloki strona główna pokazuje i w jakiej kolejności. Działa z natywnymi modułami PrestaShop; moduły zewnętrzne są wyświetlane, ale konfiguruje się je na ich własnej stronie.

**Mega menu (Pro)**: Claude tworzy mega menu na podstawie opisu: pozycje otwierające szerokie panele do 6 kolumn z kategoriami, linkami z etykietami, zdjęciami, produktami (wybranymi, z kategorii, nowościami, bestsellerami, promocjami), markami i tekstem. Zapisywane jest jako szkic z prywatnym linkiem podglądu, a następnie publikowane zamiast natywnego menu (i wycofywane jednym kliknięciem). Na telefonie staje się akordeonem. Zewnętrzne mega menu (PrestaHero, Iqit...) są odczytywane, aby Claude mógł je opisać lub odtworzyć, ale nie są zmieniane.

**Kombinacje**: lista i tworzenie atrybutów (rozmiar, kolor...) oraz ich wartości, tworzenie do 100 kombinacji naraz z wpływem na cenę, indeksem, EAN i stanem magazynowym, edycja kombinacji.

**Zdjęcia**: dodawanie zdjęcia z adresu internetowego, pisanie tekstów alternatywnych, wybór zdjęcia głównego, zmniejszanie zbyt ciężkich oryginałów, wyśrodkowanie ich na kwadratowym tle i ponowne generowanie wszystkich miniatur.

**Wzbogacanie**: cechy, marki, produkty powiązane, promocje (procentowe lub kwotowe, z datami) oraz pełny audyt katalogu wskazujący, co hamuje sprzedaż i widoczność sklepu w wyszukiwarkach.

**Sprzedaż krzyżowa i koszyk**: produkty powiązane (dodawanie, usuwanie, zastępowanie, do 50 produktów naraz), sugestie „często kupowane razem” wyliczane na podstawie rzeczywistych zamówień sklepu oraz natywny blok „klienci kupili również” na karcie produktu i w koszyku jako dodatkowe zakupy. Inne moduły upsellingowe są wykrywane i wymieniane, ale nigdy zmieniane.

**Zamówienia i śledzenie**: wyszukiwanie zamówienia (numer, klient, numer przesyłki), odczyt zamówienia z historią i linkiem do śledzenia przesyłki u przewoźnika, zmiana statusu, ustawienie numeru przesyłki.

**Strony CMS i tłumaczenia**: odczyt i edycja stron CMS, edycja do 50 produktów naraz (ceny, statusy, teksty lub tłumaczenia).

**Codzienne zarządzanie**: podsumowanie sprzedaży za wybrany okres, niskie stany magazynowe, wyszukiwanie i karta klienta, odczyt wiadomości obsługi klienta i odpowiedź e-mailem (po Państwa akceptacji), tworzenie kodów rabatowych.

**Motyw potomny**: tworzenie szkicu motywu potomnego, odczyt i przeszukiwanie plików motywu, audyt nagłówków i ścieżki nawigacyjnej opublikowanej strony, zmiany w szablonach, CSS i JavaScript szkicu, przywracanie kopii zapasowej.

W menu Claude dostępne są też trzy gotowe przewodniki: Podsumowanie dnia, Optymalizacja katalogu, Porządkowanie nagłówków Hn i ścieżki nawigacyjnej.

## Czego Claude nie potrafi

- Zwrócić pieniędzy klientowi ani dotknąć płatności.
- Zmienić opublikowanego motywu ani motywu nadrzędnego.
- Opublikować motywu: to Państwo klikają, w zakładce Motyw potomny.
- Usunąć produktu, klienta ani zamówienia.
- Uzyskać większych uprawnień niż pracownik, który go połączył.

## Zabezpieczenia

- Każde narzędzie zmieniające sklep jest oznaczone jako wrażliwe: Claude prosi o potwierdzenie przed jego użyciem.
- Uprawnienia odpowiadają profilowi PrestaShop pracownika, który autoryzował połączenie, i są sprawdzane przy każdym wywołaniu.
- Każda zmiana motywu jest zapisywana w kopii zapasowej. Plików PHP i konfiguracji motywu nie można zmieniać. Szablony są sprawdzane przed zapisem: odrzucane są niezamknięte tagi Smarty, funkcje PHP spoza krótkiej listy dozwolonych, wywołania klas, stałe i odczyty plików.
- W każdej chwili mogą Państwo wyłączyć serwer i odłączyć każdego asystenta, w zakładce Połączenie.
- Żadne dane nie są wysyłane do Piskee.

## Publikacja motywu

1. Prosimy poprosić Claude o pracę na szkicu motywu potomnego.
2. Następnie należy otworzyć Piskee, zakładkę Motyw potomny, i kliknąć **Opublikuj** obok szkicu.
3. Warto sprawdzić stronę główną, jedną kategorię i jeden produkt. Jeśli coś wygląda źle, wystarczy kliknąć **Powrót do poprzedniego motywu**.

Zmienia się tylko motyw: moduły, ich ustawienia, pozycje hooków, rozmiary zdjęć i układy stron pozostają bez zmian. Szkic można opublikować tylko w miejsce motywu z tej samej rodziny (z tym samym motywem nadrzędnym).

## Darmowy czy Pro

Wersja darmowa pozwala na 100 zmian miesięcznie na sklep; odczyt, wyszukiwanie i audyty są bez limitu. Licznik zeruje się 1. dnia miesiąca.

Piskee Pro (29 € netto miesięcznie lub 290 € netto rocznie z 2 miesiącami gratis, cena startowa gwarantowana dożywotnio dla pierwszych 100 klientów, bez zobowiązań) znosi limit i dodaje historię każdej zmiany z możliwością cofnięcia, dane strukturalne (produkt, FAQ, artykuły) i artykuły na blogu pisane przez Claude, przekierowania 301 oraz wsparcie priorytetowe. Licencja Pro obejmuje jeden sklep: w multistore każdy dodatkowy sklep kosztuje 10 € netto miesięcznie. Sesja wdrożeniowa z agencją kosztuje 150 € netto.

Subskrypcja: [miesięczna](https://buy.stripe.com/28EbJ1bO4121gcQ0kZ9EI00) · [roczna](https://buy.stripe.com/28EcN59FW5ihd0Ed7L9EI02) · [dodatkowy sklep](https://buy.stripe.com/aFa6oH19qaCBgcQ2t79EI04) · [wdrożenie](https://buy.stripe.com/3cI14nbO47qp7GkffT9EI03).

Klucz licencyjny otrzymany e-mailem należy wkleić w zakładce Darmowy / Pro.

## Rozwiązywanie problemów

- **„Ta prośba o połączenie jest nieprawidłowa”** podczas autoryzacji: należy usunąć łącznik w Claude, a następnie dodać go ponownie.
- **Stały błąd 401**: niektóre hostingi usuwają nagłówek Authorization. Należy dodać `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` na początku pliku `.htaccess`.
- **Claude nie widzi nowych narzędzi**: należy otworzyć nową rozmowę.
- **Błąd przy otwieraniu ekranu autoryzacji**: prosimy sprawdzić, czy sklep nie jest w trybie konserwacji i czy żadna zapora nie blokuje adresów `/.well-known/`.
- **Zmiana w motywie nie jest widoczna**: to normalne, dopóki szkic nie zostanie opublikowany.

## Potrzebują Państwo pomocy?

Zespół Piskee odpowiada na pytania dotyczące modułu i wspiera sklepy, które chcą pójść dalej: audyty SEO i GEO, treści, Google Ads, rozwiązania na zamówienie.

- Kontakt: [piskee.com/contact](https://www.piskee.com/contact/)
- E-mail: louisalexandre@piskee.com
- WhatsApp: +33 7 69 15 39 92
- Bezpłatny audyt SEO i GEO w 48 h: [piskee.com](https://www.piskee.com/)

## Informacje prawne

Piskee dla PrestaShop to niezależny moduł wydawany przez Piskee (Chantilly, Francja). Nie jest oficjalnym produktem PrestaShop SA ani Anthropic, PBC, które go nie opracowały, nie zweryfikowały ani nie zatwierdziły i nie ponoszą za niego żadnej odpowiedzialności. PrestaShop jest znakiem towarowym PrestaShop SA; Claude jest znakiem towarowym Anthropic, PBC; nazwy te są używane wyłącznie w celu wskazania kompatybilności. Moduł jest dostarczany „w stanie, w jakim jest”, bez gwarancji, w granicach przewidzianych przez prawo i licencję AFL-3.0. Za działania podjęte i zatwierdzone przez sprzedawcę odpowiada sprzedawca: zalecamy regularne tworzenie kopii zapasowych i sprawdzanie zmian przed ich zaakceptowaniem.
