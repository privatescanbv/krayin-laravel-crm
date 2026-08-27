{{-- Shared person suggestion UI helpers + create-lead suggest API --}}
@pushOnce('scripts')
@verbatim
    <script type="module">
        window.adminc = window.adminc || {};

        if (!window.adminc.personSuggestionHelpers) {
            const reasonLabels = {
                email: 'E-mail',
                phone: 'Telefoon',
                last_name: 'Achternaam',
                first_name_similar: 'Voornaam lijkt',
                first_name_differs: 'Voornaam verschilt',
                dob: 'Geboortedatum',
                postal_code: 'Postcode',
            };

            window.adminc.personSuggestionHelpers = {
                isStrongSuggestion(person) {
                    const reasons = person?.match_reasons || [];
                    return reasons.includes('email') || reasons.includes('phone');
                },

                suggestionReasonLabel(reason) {
                    return reasonLabels[reason] || reason;
                },

                /**
                 * Strong matches (email/phone) first, then by match_score — then cap at limit.
                 * Prevents name-only crowding from hiding phone/email hits.
                 */
                rankAndLimitSuggestions(suggestions, limit = 10) {
                    const items = Array.isArray(suggestions) ? [...suggestions] : [];
                    items.sort((a, b) => {
                        const strongDiff = (this.isStrongSuggestion(b) ? 1 : 0) - (this.isStrongSuggestion(a) ? 1 : 0);
                        if (strongDiff !== 0) {
                            return strongDiff;
                        }
                        return ((b.match_score_percentage || b.match_score || 0) - (a.match_score_percentage || a.match_score || 0));
                    });
                    return items.slice(0, limit);
                },

                personSuggestionSections(suggestions) {
                    const items = suggestions || [];
                    const strong = items.filter((s) => this.isStrongSuggestion(s));
                    const possible = items.filter((s) => !this.isStrongSuggestion(s));
                    if (strong.length && possible.length) {
                        return [
                            { key: 'strong', title: 'Waarschijnlijk dezelfde persoon', items: strong },
                            { key: 'possible', title: 'Mogelijke matches', items: possible },
                        ];
                    }
                    return [{ key: 'all', title: null, items }];
                },
            };
        }

        if (!window.adminc.fetchPersonSuggestionsFromFields) {
            /**
             * Auto-suggest persons from unsaved lead form fields (create lead).
             * Uses the same server-side PersonSuggestionService as edit lead.
             *
             * @param {object} payload Lead-like fields (first_name, emails, phones, address, ...)
             * @returns {Promise<object[]>}
             */
            window.adminc.fetchPersonSuggestionsFromFields = async function(payload = {}) {
                const response = await axios.post('/admin/contacts/persons/suggest', payload);
                const result = response?.data?.data ?? response?.data ?? [];
                const list = Array.isArray(result) ? result : [];
                const helpers = window.adminc.personSuggestionHelpers;
                return helpers
                    ? helpers.rankAndLimitSuggestions(list, 10)
                    : list.slice(0, 10);
            };
        }
    </script>
@endverbatim
@endPushOnce
