/** Minimal window surface the extractor needs – lets unit tests pass a jsdom window. */
export type ExtractorWindow = Window & typeof globalThis;
export type { MedulaExtraction, MedulaProbe } from '../shared/types';
