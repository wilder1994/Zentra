import { MarkerClusterer, SuperClusterAlgorithm } from '@googlemaps/markerclusterer';
import { numberDropIcon } from './map-pins';

/** Colores de los grupos; el grupo toma el tono más grave de sus pines (orden de TONE_ORDER). */
export const CLUSTER_TONES = {
    red: '#dc2626',
    orange: '#ea580c',
    amber: '#d97706',
    sky: '#2563eb',
    emerald: '#16a34a',
    teal: '#0d9488',
    wine: '#991b1b',
    violet: '#8b5cf6',
    indigo: '#6366f1',
    cyan: '#0891b2',
    slate: '#475569',
};

const TONE_ORDER = ['red', 'orange', 'amber', 'sky', 'emerald', 'teal', 'wine', 'violet', 'indigo', 'cyan', 'slate'];

function toneOf(markers) {
    let best = TONE_ORDER.length - 1;
    markers.forEach((marker) => {
        const index = TONE_ORDER.indexOf(marker.zentraTone || 'slate');
        if (index >= 0 && index < best) {
            best = index;
        }
    });

    return TONE_ORDER[best];
}

function samePlace(cluster) {
    const bounds = cluster.bounds;
    if (!bounds) {
        return false;
    }
    const ne = bounds.getNorthEast();
    const sw = bounds.getSouthWest();

    return Math.abs(ne.lat() - sw.lat()) < 0.00005 && Math.abs(ne.lng() - sw.lng()) < 0.00005;
}

/**
 * Agrupa pines según el zoom: lejos se juntan en una gota con el total, al acercarse se separan.
 * Cada pin puede llevar `marker.zentraTone` (ver CLUSTER_TONES).
 * `onSamePlace(markers, position, clusterMarker)` se llama si el grupo está en un mismo punto o ya no se puede acercar más.
 */
export function createClusterer(map, { maxZoom = 16, radius = 64, onSamePlace = null } = {}) {
    return new MarkerClusterer({
        map,
        markers: [],
        algorithm: new SuperClusterAlgorithm({ maxZoom, radius }),
        renderer: {
            render(cluster) {
                const tone = toneOf(cluster.markers);

                return new google.maps.Marker({
                    position: cluster.position,
                    icon: numberDropIcon(CLUSTER_TONES[tone] || CLUSTER_TONES.slate, cluster.count),
                    title: cluster.count + ' en esta zona · clic para acercar',
                    zIndex: Number(google.maps.Marker.MAX_ZINDEX) + cluster.count,
                });
            },
        },
        onClusterClick(event, cluster, clusterMap) {
            const before = clusterMap.getZoom() || 0;
            if (samePlace(cluster) || before >= maxZoom) {
                onSamePlace?.(cluster.markers, cluster.position, cluster.marker);

                return;
            }
            // Mapas bajos: el encuadre con margen puede no acercar; entonces se acerca 2 niveles sobre el grupo.
            if (cluster.bounds) {
                clusterMap.fitBounds(cluster.bounds, 12);
            }
            google.maps.event.addListenerOnce(clusterMap, 'idle', () => {
                if ((clusterMap.getZoom() || 0) <= before) {
                    clusterMap.setCenter(cluster.position);
                    clusterMap.setZoom(Math.min(before + 2, maxZoom + 1));
                }
            });
        },
    });
}
