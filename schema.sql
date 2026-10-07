-- Pstryk — godzinowe ceny, zuzycie i koszty.
-- Uzycie: mysql pstryk < schema.sql
-- Kazdy wiersz = jedna godzina; ts_* = POCZATEK godziny (np. 14:00 = 14:00-15:00).
-- Kluczem jest czas UTC, bo przy zmianie czasu na zimowy godzina 02:00 czasu polskiego wystepuje dwa razy.
-- Ceny i koszty w PLN, energia w kWh.

CREATE TABLE IF NOT EXISTS `pstryk_hourly` (
  `ts_utc`               datetime NOT NULL,
  `ts_local`             datetime NOT NULL,
  `price_gross`          decimal(10,6) DEFAULT NULL,  -- pelna cena brutto zl/kWh (energia + dystrybucja + oplata Pstryk + akcyza + VAT)
  `price_tge`            decimal(10,6) DEFAULT NULL,  -- cena energii na TGE netto zl/kWh
  `price_prosumer_gross` decimal(10,6) DEFAULT NULL,  -- cena sprzedazy energii (prosument) brutto zl/kWh
  `is_cheap`             tinyint DEFAULT NULL,        -- 1 = tania godzina wg Pstryka
  `is_expensive`         tinyint DEFAULT NULL,        -- 1 = droga godzina wg Pstryka
  `kwh_import`           decimal(10,5) DEFAULT NULL,  -- pobor z sieci
  `kwh_export`           decimal(10,5) DEFAULT NULL,  -- oddanie do sieci (PV)
  `cost_import`          decimal(10,4) DEFAULT NULL,  -- pelny koszt poboru brutto
  `cost_energy_net`      decimal(10,6) DEFAULT NULL,  -- sam koszt energii netto (bez dystrybucji i oplat)
  `value_sold`           decimal(10,4) DEFAULT NULL,  -- wartosc energii oddanej
  `cost_balance`         decimal(10,4) DEFAULT NULL,  -- bilans: koszt poboru - wartosc oddanej
  `json`                 text DEFAULT NULL,           -- cala odpowiedz API dla godziny (wszystkie skladniki cen i kosztow)
  PRIMARY KEY (`ts_utc`),
  KEY `idx_local` (`ts_local`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przyklady:
-- koszt i zuzycie dzienne:
--   SELECT DATE(ts_local) dzien, SUM(kwh_import) kwh, SUM(cost_import) zl FROM pstryk_hourly GROUP BY dzien ORDER BY dzien DESC;
-- najtansze godziny jutro:
--   SELECT ts_local, price_gross FROM pstryk_hourly WHERE DATE(ts_local) = CURDATE() + INTERVAL 1 DAY ORDER BY price_gross LIMIT 5;
-- dowolny skladnik z JSON, np. oplata dystrybucyjna:
--   SELECT ts_local, JSON_VALUE(json, '$.pricing.dist_price') FROM pstryk_hourly ORDER BY ts_utc DESC LIMIT 24;
