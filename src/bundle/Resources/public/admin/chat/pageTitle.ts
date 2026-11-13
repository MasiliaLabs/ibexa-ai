/** The title the admin shows for the content in view, to name the page in the chip. Null when the page shows none. */
export function pageTitle(): string | null {
    const title = document.querySelector('.ibexa-page-title__title')?.textContent?.trim();

    return title !== undefined && title !== '' ? title : null;
}
