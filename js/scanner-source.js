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
};
