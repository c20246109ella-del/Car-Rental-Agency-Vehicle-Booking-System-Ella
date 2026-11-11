type ApiResponse<TData = unknown> = {
    message?: string;
    data?: TData;
    errors?: Record<string, string | string[]>;
};

export class ApiFormError extends Error {
    constructor(
        message: string,
        readonly errors: Record<string, string>,
    ) {
        super(message);
        this.name = 'ApiFormError';
    }
}

export async function submitJsonForm<TData = unknown>(
    url: string,
    data: Record<string, string>,
    method: 'POST' | 'PUT' = 'POST',
): Promise<ApiResponse<TData>> {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
        throw new Error('The security token is missing. Refresh the page and try again.');
    }

    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(data),
    });

    let payload: ApiResponse<TData>;

    try {
        payload = (await response.json()) as ApiResponse<TData>;
    } catch {
        throw new Error('The server returned an unexpected response. Please try again.');
    }

    if (!response.ok) {
        const errors = Object.fromEntries(
            Object.entries(payload.errors ?? {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
        );

        throw new ApiFormError(payload.message ?? 'Unable to save your changes. Please try again.', errors);
    }

    return payload;
}
