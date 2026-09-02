import axios from 'axios';

const api = axios.create({
    headers: { Accept: 'application/json' },
});

const DEFAULT_ERROR_MESSAGE = 'Não foi possível carregar os dados.';

export async function getJson<T>(
    url: string,
    params?: Record<string, unknown>,
    signal?: AbortSignal,
): Promise<T> {
    try {
        const response = await api.get<T>(url, { params, signal });

        return response.data;
    } catch (error) {
        if (axios.isCancel(error)) {
            throw error;
        }

        throw new Error(DEFAULT_ERROR_MESSAGE);
    }
}
