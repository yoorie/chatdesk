import { type LocationMapState } from './types';

export const LOCATION_MAP_WIDTH = 336;

export const LOCATION_MAP_HEIGHT = 192;

export const LOCATION_TILE_SIZE = 256;

export const clampLocationLat = (lat: number) => Math.max(-85, Math.min(85, lat));

export const clampLocationLng = (lng: number) => ((((lng + 180) % 360) + 360) % 360) - 180;

export const locationToWorldPixel = (lat: number, lng: number, zoom: number) => {
  const scale = LOCATION_TILE_SIZE * 2 ** zoom;
  const clampedLat = clampLocationLat(lat);
  const sin = Math.sin((clampedLat * Math.PI) / 180);

  return {
    x: ((clampLocationLng(lng) + 180) / 360) * scale,
    y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale,
  };
};

export const worldPixelToLocation = (x: number, y: number, zoom: number) => {
  const scale = LOCATION_TILE_SIZE * 2 ** zoom;
  const lng = (x / scale) * 360 - 180;
  const n = Math.PI - (2 * Math.PI * y) / scale;
  const lat = (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));

  return {
    lat: clampLocationLat(lat),
    lng: clampLocationLng(lng),
  };
};

export const locationMapTiles = (center: LocationMapState) => {
  const centerPixel = locationToWorldPixel(center.lat, center.lng, center.zoom);
  const minX = Math.floor((centerPixel.x - LOCATION_MAP_WIDTH / 2) / LOCATION_TILE_SIZE);
  const maxX = Math.floor((centerPixel.x + LOCATION_MAP_WIDTH / 2) / LOCATION_TILE_SIZE);
  const minY = Math.floor((centerPixel.y - LOCATION_MAP_HEIGHT / 2) / LOCATION_TILE_SIZE);
  const maxY = Math.floor((centerPixel.y + LOCATION_MAP_HEIGHT / 2) / LOCATION_TILE_SIZE);
  const tileCount = 2 ** center.zoom;
  const tiles: Array<{ key: string; url: string; left: number; top: number }> = [];

  for (let x = minX; x <= maxX; x += 1) {
    for (let y = minY; y <= maxY; y += 1) {
      if (y < 0 || y >= tileCount) continue;
      const wrappedX = ((x % tileCount) + tileCount) % tileCount;
      tiles.push({
        key: `${center.zoom}-${x}-${y}`,
        url: `https://tile.openstreetmap.org/${center.zoom}/${wrappedX}/${y}.png`,
        left: x * LOCATION_TILE_SIZE - (centerPixel.x - LOCATION_MAP_WIDTH / 2),
        top: y * LOCATION_TILE_SIZE - (centerPixel.y - LOCATION_MAP_HEIGHT / 2),
      });
    }
  }

  return tiles;
};
