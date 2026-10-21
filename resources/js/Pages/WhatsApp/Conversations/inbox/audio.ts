let lameLoader: Promise<any> | null = null;

export const loadLameEncoder = (): Promise<any> => {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Voice encoder is only available in the browser.'));
  }

  const existing = (window as any).lamejs?.Mp3Encoder;
  if (existing) {
    return Promise.resolve((window as any).lamejs);
  }

  if (!lameLoader) {
    lameLoader = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = '/vendor/lame.all.js';
      script.async = true;
      script.onload = () => {
        const lame = (window as any).lamejs;
        if (lame?.Mp3Encoder) {
          resolve(lame);
        } else {
          reject(new Error('Voice encoder did not initialize.'));
        }
      };
      script.onerror = () => {
        lameLoader = null;
        script.remove();
        reject(new Error('Voice encoder could not be loaded.'));
      };
      document.head.appendChild(script);
    });
  }

  return lameLoader;
};

export const flattenAudioSamples = (chunks: Float32Array[]): Float32Array => {
  const length = chunks.reduce((total, chunk) => total + chunk.length, 0);
  const samples = new Float32Array(length);
  let offset = 0;
  chunks.forEach((chunk) => {
    samples.set(chunk, offset);
    offset += chunk.length;
  });

  return samples;
};

export const floatTo16BitPcm = (samples: Float32Array): Int16Array => {
  const pcm = new Int16Array(samples.length);
  for (let index = 0; index < samples.length; index += 1) {
    const sample = Math.max(-1, Math.min(1, samples[index]));
    pcm[index] = sample < 0 ? sample * 0x8000 : sample * 0x7fff;
  }

  return pcm;
};

export const encodeMp3 = (lame: any, samples: Float32Array, sampleRate: number): Blob => {
  const encoder = new lame.Mp3Encoder(1, sampleRate, 64);
  const pcm = floatTo16BitPcm(samples);
  const chunks: ArrayBuffer[] = [];
  const blockSize = 1152;

  for (let offset = 0; offset < pcm.length; offset += blockSize) {
    const buffer = encoder.encodeBuffer(pcm.subarray(offset, offset + blockSize));
    if (buffer.length > 0) {
      chunks.push(buffer.slice().buffer as ArrayBuffer);
    }
  }

  const flush = encoder.flush();
  if (flush.length > 0) {
    chunks.push(flush.slice().buffer as ArrayBuffer);
  }

  return new Blob(chunks, { type: 'audio/mpeg' });
};

export const formatAudioTime = (seconds: number): string => {
  if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
  const minutes = Math.floor(seconds / 60);
  const remainder = Math.floor(seconds % 60)
    .toString()
    .padStart(2, '0');
  return `${minutes}:${remainder}`;
};
