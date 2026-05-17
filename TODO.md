# TODO - Receipt formatting refactor

- [ ] Inspect receipt layout code paths in `templates/ReceiptPdf.php` (header/body/footer/table/label-value)
- [ ] Refactor `templates/ReceiptPdf.php` to use thermal receipt page sizing (58mm) + consistent margins/fonts/line-height
- [ ] Replace layout with modular renderers: header block, meta label/value block, items table (3 columns), totals block, footer
- [ ] Implement robust wrapped text rendering that advances Y by computed row height (prevents overlap)
- [ ] Ensure amounts are right-aligned and totals align correctly
- [ ] Ensure long descriptions wrap within column width without breaking layout
- [ ] Add POS-friendly styling (monospace fallback, compact dividers)
- [ ] Test by generating existing receipts and verifying alignment/size visually

