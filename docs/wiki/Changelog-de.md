# Änderungsverlauf

## 1.0.0 — 2026-11-10

- Neu: Persönliche gespeicherte Berichtsansichten, klare aktive Filter und gemeinsame Zeiträume; Tages-, Wochen- und Monatsdiagramme mit genauer Wertetabelle.
- Neu: Native Block-Vorlagen und bebilderte Anleitungen zur Inhaltspflege, zum Zeitraumvergleich und zu Jahresrankings.
- Verbessert: Wiederverwendete Berichtsabfragen, Ranking-Caches mit begrenzter Neuberechnung, Wartungsdiagnose, Importzusammenfassungen und kontextbezogene CSV-Exporte.
- Verbessert: Zeitlich begrenzte Messsignaturen und begrenzte laufende Referrer-Erfassung; verständliche Hinweise zu Cache-Konfiguration und Messgrenzen.
- Verbessert: deckerweb Library 1.3.0 und Updater 2.4.0; Updates installierter Plugins bleiben unabhängig vom Entdeckungskatalog.
- Behoben: Registrierte Shortcode-Aufrufe, Lebenszyklus-Isolation der Zielnetzwerke, Vergleiche abgeschlossener Tage, verwaiste Cache-Sperren und Integritätsprüfung der Länderdatenbank.
- Sonstiges: Nach Installation oder Update den vollständigen Seiten-/CDN-HTML-Cache leeren. HTML kürzer als sieben Tage zwischenspeichern; ein Tag wird empfohlen. WordPress 7.1.2 und PHP 8.2 bleiben Mindestvoraussetzungen.

## 0.9.1 — 2026-10-21

- Verbessert: deckerweb Library 1.1.0 und Updater 2.2.1 mit freigegebenen Katalogdownloads und lokalisierten Hinweisen aktualisiert.
- Verbessert: Messskript verzögert laden, damit die Darstellung der Seite nicht blockiert wird.
- Behoben: GitHub-Updateprüfungen bleiben verfügbar, wenn ein anderes Plugin zuerst einen älteren Updater lädt.
- Behoben: Ausdrückliche Maskierung der SVG-Koordinaten und sichere Tabellenkennungen bei der Speicherprüfung. Übersetzerhinweise vervollständigt.

## 0.9.0 — 2026-10-19

- Neu: Eigenständige, datensparsame Website-Statistik mit Inhaltsrankings, Jahresrückblicken und Aufrufspalten für die redaktionelle Arbeit.
- Verbessert: Diagramme, modulare Dashboard-Widgets, öffentliche Ausgabeblöcke, Zugriffssteuerung und Dokumentation. Library 1.0.0 und Updater 2.1.0 für den GitHub-Vertrieb integriert. Schnellere breite Jahresrankings, normale Inhaltslisten und Diagrammabfragen ohne zusätzliche Schreibzugriffe oder Indizes beim Messen. Begrenzte Bereinigungsschritte auch bei größeren Rückständen.
- Behoben: Statistikzugriff, Aufrufspalten, Diagrammdarstellung und die Zuordnung von Hinweisen und Bedienelementen. Schriftgrößen in der Blockvorschau und lokalisierte Dokumentationsdialoge. Vollständiger Rollback und sichere Zustellwiederholungen bei Datenbankfehlern oder Verbindungsabbrüchen, auch für Suchzähler. Atomare Import-Transaktionen und Prüfung konkurrierender Importquellen. Zähler in Beitragslisten, später registrierte Inhaltstypen, dichte Jahresdiagramme und mobile Einstellungen.

## 0.1.0 – 0.8.0

- Sonstiges: Frühe Testfassungen der eigenständigen Website-Statistik.
