import { reactive } from 'vue';

export type HouseRoom = {
    id: string;
    label: string;
    polygon: number[][];
    floor_material: string;
    render_floor: boolean;
    stair_item_id: string | null;
};

export type HouseWall = {
    id: number;
    name: string | null;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    thickness: number;
    outside_dx: number | null;
    outside_dy: number | null;
};

export type HouseFloor = {
    id: string;
    label: string;
    length: number;
    width: number;
    display_mirrored: boolean;
    rooms: HouseRoom[];
    walls: HouseWall[];
};

export type HouseGeometry = { floors: HouseFloor[] };

export const house = reactive<HouseGeometry>({ floors: [] });

export async function refreshHouse(): Promise<void> {
    const response = await fetch('/dashboard/house', {
        headers: { Accept: 'application/json' },
        signal: AbortSignal.timeout(15000),
    });

    if (! response.ok) {
        throw new Error('House geometry could not be loaded.');
    }

    const data = await response.json() as HouseGeometry;
    if (! Array.isArray(data.floors) || ! data.floors.length) {
        throw new Error('House geometry is empty.');
    }

    house.floors.splice(0, house.floors.length, ...data.floors);
}
