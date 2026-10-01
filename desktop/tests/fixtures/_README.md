# Medula HTML fixtures — SYNTHETIC ONLY

These files emulate the *structure* of the Medula Optik screens as documented in
`app/sgk.php` (header comment) and `kopru-eklenti/icerik.js`. They are NOT copies
of real SGK pages and contain NO real patient, doctor, facility or prescription
data (spec §46–47). Names, numbers and IDs are invented. The T.C. number
`12345678901` is the same obviously-fake placeholder already used in `app/sgk.php`.

`*.metin.txt` are the golden extractor outputs for the matching `.html` file
(regenerate with `node tests/fixtures/regenerate.mjs` only after an intentional
extractor change, and review the diff).

If you capture a new real screen to improve a fixture: rebuild it by hand with
invented values; never commit a saved page, HAR file or screenshot.
