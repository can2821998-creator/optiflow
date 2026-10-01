/**
 * Choose which Medula frame holds the prescription (spec §35).
 * The legacy extension injected into all_frames and let the user click the
 * button inside the right frame; we probe every allowed frame and pick the
 * best-scoring one that the detector accepts. Pure → unit-tested.
 */
import type { MedulaProbe } from '../../shared/types';

export interface FrameProbe<F> {
  frame: F;
  probe: MedulaProbe;
  isTop: boolean;
}

export type FrameChoice<F> = { kind: 'ok'; frame: F; probe: MedulaProbe } | { kind: 'login' } | { kind: 'not-found' };

export function chooseFrame<F>(probes: FrameProbe<F>[]): FrameChoice<F> {
  const detected = probes.filter((p) => p.probe.detectedPrescription);
  if (detected.length > 0) {
    detected.sort(
      (a, b) =>
        b.probe.score - a.probe.score ||
        b.probe.extractedFieldCount - a.probe.extractedFieldCount ||
        b.probe.textLength - a.probe.textLength,
    );
    const best = detected[0]!;
    return { kind: 'ok', frame: best.frame, probe: best.probe };
  }
  if (probes.some((p) => p.probe.looksLikeLogin)) return { kind: 'login' };
  return { kind: 'not-found' };
}

/** Aggregate for the toolbar badge. */
export function summarizeProbes<F>(probes: FrameProbe<F>[]): MedulaProbe | undefined {
  const c = chooseFrame(probes);
  if (c.kind === 'ok') return c.probe;
  if (probes.length === 0) return undefined;
  return {
    detectedPrescription: false,
    score: Math.max(...probes.map((p) => p.probe.score)),
    extractedFieldCount: 0,
    looksLikeLogin: c.kind === 'login',
    textLength: 0,
  };
}
