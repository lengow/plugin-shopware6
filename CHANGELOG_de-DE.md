# v1.2.0
- Bugfix: [import] fix Produkt zum Shopware Warenkorb hinzufügen
- Bugfix: [action] Fix id Action ist keine ganze Zahl

# v1.1.3
- Bugfix: [install] Installations-Plugin korrigiert
- Bugfix: [global] Ersetze Klasse EntityRepositoryInterface durch EntityRepository
- Bugfix: [front] Verwendet neuen Klassennamen für die Icon-Bibliothek von Shopware
- Bugfix: [import & export] Neue Annotation für Route Scope verwenden
- Bugfix: [import] Verwendung der RetryableQuery Methode korrigiert
- Bugfix: [import] id Lieferadresse nicht für DB Sharding verwenden
- Bugfix: [import] Teilerstattungsstatus hinzufügen
- Bugfix: [import] Bestellung neu importieren, wenn Mehrwertsteuernummer différent ist
- Merkmal: Hinzufügen einer konfigurierbaren URL-Umgebung zur Verbindung mit Lengow in den Einstellungen und Hinzufügen einer zugänglichen Einstellung auf der Homepage des Moduls
- Feature: Details der geänderten Datei in der Toolbox abrufen

# v1.1.2
- Bugfix: [import] Korrektur des Suchträgercodes

# v1.1.1
- Feature: Hinzufügen der PHP-Version in der Toolbox
- Feature: Änderung der Fallback-URLs des Lengow Help Centers
- Feature: Hinzufügen eines Aktualisierungsdatums für ein zusätzliches Feld in der externen Toolbox

# v1.1.0
- Feature: Integration der Auftragssynchronisation in den Webservice der Toolbox
- Feature: Abrufen des Status einer Bestellung im Webservice der Toolbox

# v1.0.2
- Feature: Auslagerung der Toolbox über den Webservice
- Feature: Kompatibilität mit Shopware 6.4 hinzugefügt
- Feature: Einrichten eines Modals für das Plugin-Update
- Bugfix: [export] html_entity_decode-Aufruf beim Abrufen der Produktbeschreibung entfernen
- Bugfix: [export] Duplizierung von Header-Feldern behoben
- Bugfix: [export] Bildabruf korrigieren

# v1.0.1
- Bugfix: [export] Fügen Sie Parameter in Produkt-SQL-Anforderungen hinzu
- Bugfix: [export] Verwenden Sie die Funktion getFeedUrl() im Produktraster

# v1.0.0
- Feature: Lengow Dashboard (Kontakt, HelpCenter und Quick-Links)
- Feature: Produktseite mit Produktauswahl nach Vertriebskanal
- Feature: Direkter Abruf des Shopware-Katalogs in Lengow
- Feature: Implementierung des Lengow-Bestellungsmanagement-Screens
- Feature: Automatische Synchronisierung von Marktplatzbestellungen zwischen Lengow und Shopware
- Feature: Verwaltung der von Marktplätzen gesendeten Bestellungen
- Feature: Anzeige der Bestellarten (Express, B2B, Versandt durch Marktplatz)
- Feature: Schnellkorrektur eines Import- oder Sendeaktionsfehlers mit dem Refresh-Button
- Feature: Verwaltung von Versand- und Stornierungsaktionen von Bestellungen
- Feature: Automatische Überprüfung der an den Marktplatz gesendeten Aktionen
- Feature: Automatisches Senden einer Aktion, wenn der erste Versand ein Fehler war
- Feature: Ein Bericht per Mail mit Fehlern beim Auftragsimport und Aktions-Upload
- Feature: Anzeige aller Lengow-Bestellinformationen für Details einer Shopware-Bestellung
- Feature: Hilfeseite mit allen notwendigen Support-Links
- Feature: Toolbox mit allen Lengow-Informationen zur Unterstützung
- Feature: Wartungsprotokolle global oder pro Tag herunterladen
- Feature: Direkte Verwaltung von Einstellungen in der Plugin-Schnittstelle
- Feature: Synchronisierung von Lengow-Konten direkt aus dem Plugin
