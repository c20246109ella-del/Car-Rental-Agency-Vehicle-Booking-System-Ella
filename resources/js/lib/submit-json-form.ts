type ApiResponse = {
    message?: string;
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

export async function submitJsonForm(url: string, data: Record<string, string>): Promise<ApiResponse> {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
        throw new Error('The security token is missing. Refresh the page and try again.');
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(data),
    });

    let payload: ApiResponse;

    try {
        payload = (await response.json()) as ApiResponse;
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
