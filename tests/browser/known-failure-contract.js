'use strict';

function matchesKnownFailure(item, error, ownSignals) {
    if (!item.knownFailure || typeof item.knownFailure.expectedError !== 'string') return false;
    let expectedError;
    try {
        expectedError = new RegExp(item.knownFailure.expectedError);
    } catch (patternError) {
        return false;
    }
    return expectedError.test(String(error.message)) && ownSignals.length === 0;
}

function assertKnownFailureMatcherContract() {
    const item = { knownFailure: { expectedError: '^component marker not visible: \\.wpsr_editor$' } };
    if (!matchesKnownFailure(item, new Error('component marker not visible: .wpsr_editor'), [])) {
        throw new Error('known-failure matcher rejected exact expected symptom');
    }
    if (matchesKnownFailure(item, new Error('unexpected login redirect: /wp-login.php'), [])) {
        throw new Error('known-failure matcher accepted unrelated assertion failure');
    }
    if (matchesKnownFailure(item, new Error('component marker not visible: .wpsr_editor'), [
        { kind: 'console-error', detail: 'new regression' },
    ])) {
        throw new Error('known-failure matcher accepted expected symptom plus unrelated runtime signal');
    }
}

module.exports = { matchesKnownFailure, assertKnownFailureMatcherContract };
