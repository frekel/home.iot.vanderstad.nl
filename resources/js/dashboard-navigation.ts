const mobileFurnitureQuery = window.matchMedia('(max-width: 750px)');
const roomLightBusy = new Set<string>();

type DashboardLight = {
    id: string;
    class: string;
    zone_name?: string;
    available: boolean | null;
    measurements: Record<string, { value: unknown; settable: boolean }>;
};

async function toggleRoomLights(dot: HTMLElement): Promise<void> {
    const row = dot.closest<HTMLButtonElement>('.room-row');
    const label = row?.querySelectorAll('span')[1]?.textContent?.trim();
    if (!label || roomLightBusy.has(label)) {
        return;
    }

    roomLightBusy.add(label);
    dot.classList.add('busy');

    try {
        const statusResponse = await fetch('/dashboard/status', {
            headers: { Accept: 'application/json' },
            signal: AbortSignal.timeout(12000),
        });
        if (!statusResponse.ok) {
            throw new Error('Homey status could not be loaded.');
        }

        const status = await statusResponse.json() as {
            connected?: boolean;
            devices?: DashboardLight[];
        };
        if (!status.connected) {
            throw new Error('Homey is not connected.');
        }

        const lights = (status.devices ?? []).filter((device) =>
            device.class === 'light'
            && device.zone_name === label
            && device.available === true
            && device.measurements.onoff?.settable === true
            && typeof device.measurements.onoff.value === 'boolean'
        );
        if (!lights.length) {
            return;
        }

        // Room toggle semantics: if at least one light is on, switch every light off.
        // If all lights are off, switch every light on.
        const desired = !lights.some((device) => device.measurements.onoff?.value === true);
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        const responses = await Promise.all(lights.map((device) => fetch(`/dashboard/lights/${encodeURIComponent(device.id)}`, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ value: desired }),
            signal: AbortSignal.timeout(15000),
        })));

        if (responses.some((response) => !response.ok)) {
            throw new Error('Not every light accepted the command.');
        }

        // Immediate visual feedback; the regular Homey poll confirms the real state.
        dot.classList.toggle('on', desired);
    } catch (error) {
        console.error('Room light toggle failed', error);
    } finally {
        dot.classList.remove('busy');
        roomLightBusy.delete(label);
    }
}

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const roomDot = target.closest<HTMLElement>('.room-row .room-dot');
    if (roomDot) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        void toggleRoomLights(roomDot);
        return;
    }

    if (!mobileFurnitureQuery.matches) {
        return;
    }

    const button = target.closest<HTMLButtonElement>('.header-right > button.lights-button:first-child');
    if (!button || !button.textContent?.trim().startsWith('Meubels')) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    window.location.assign('/measure');
}, true);
