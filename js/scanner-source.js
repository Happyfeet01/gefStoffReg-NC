// Build with: npm ci && npm run build:scanner
import { BrowserMultiFormatOneDReader, BarcodeFormat } from '@zxing/browser';
import { DecodeHintType } from '@zxing/library';

window.GefahrstoffScanner = {
  start(video, onResult) {
    const formats = [BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.CODE_128];
    const hints = new Map([[DecodeHintType.POSSIBLE_FORMATS, formats]]);
    const reader = new BrowserMultiFormatOneDReader(hints, { delayBetweenScanAttempts: 250, delayBetweenScanSuccess: 250 });
    return reader.decodeFromConstraints(
      { audio: false, video: { facingMode: { ideal: 'environment' } } },
      video,
      (result, _error, controls) => { if (result) onResult(result.getText(), controls); },
    );
  },
  async decodePhoto(file) {
    const hints = new Map([
      [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.CODE_128]],
      [DecodeHintType.TRY_HARDER, true],
    ]);
    const reader = new BrowserMultiFormatOneDReader(hints);
    let bitmap;
    let imageUrl;
    try {
      let image;
      if ('createImageBitmap' in window) {
        try { bitmap = await createImageBitmap(file); } catch { /* use image element below */ }
      }
      if (bitmap) {
        image = bitmap;
      } else {
        imageUrl = URL.createObjectURL(file);
        image = new Image();
        image.src = imageUrl;
        await image.decode();
      }
      const scale = Math.min(1, 2400 / Math.max(image.width, image.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(image.width * scale));
      canvas.height = Math.max(1, Math.round(image.height * scale));
      canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
      return reader.decodeFromCanvas(canvas).getText();
    } finally {
      bitmap?.close();
      if (imageUrl) URL.revokeObjectURL(imageUrl);
    }
  },
};
