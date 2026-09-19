import { Controller } from '@hotwired/stimulus';

/*
 * Marks the clicked item of a list as the current one. The list lives outside of
 * the Turbo Frame its links update, so the server never gets to re-render it.
 */
export default class extends Controller {
    static targets = ['item'];

    select({ currentTarget }) {
        this.itemTargets.forEach((item) => item.setAttribute('aria-current', item === currentTarget ? 'true' : 'false'));
    }
}
