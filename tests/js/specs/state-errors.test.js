import { describe, expect, it } from 'vitest';
import Errors from '../../../resources/admin/errors/Errors.js';

describe('central admin error state helper', () => {
    it('records exact payload and exposes field/first/has views', () => {
        expect.assertions(8);
        const wpsrErrors = new Errors();
        const wpsrPayload = {
            email: { required: 'Email required', format: 'Email invalid' },
            title: ['Title required'],
        };

        expect(wpsrErrors.errors).toEqual({});
        expect(wpsrErrors.has('email')).toBe(false);
        wpsrErrors.record(wpsrPayload);
        expect(wpsrErrors.errors).toBe(wpsrPayload);
        expect(wpsrErrors.get('email')).toBe(wpsrPayload.email);
        expect(wpsrErrors.first('email')).toBe('Email required');
        expect(wpsrErrors.first('title')).toBe('Title required');
        expect(wpsrErrors.has('title')).toBe(true);
        expect(wpsrErrors.get('missing')).toBeUndefined();
    });

    it('clears one field without changing sibling state', () => {
        expect.assertions(5);
        const wpsrErrors = new Errors();
        const wpsrPayload = {
            email: { required: 'Email required' },
            title: { required: 'Title required' },
        };
        wpsrErrors.record(wpsrPayload);

        wpsrErrors.clear('email');

        expect(wpsrErrors.errors).toBe(wpsrPayload);
        expect(wpsrErrors.get('email')).toBeUndefined();
        expect(wpsrErrors.has('email')).toBe(false);
        expect(wpsrErrors.first('title')).toBe('Title required');
        expect(wpsrErrors.has('title')).toBe(true);
    });

    it('clears all fields into fresh empty state', () => {
        expect.assertions(4);
        const wpsrErrors = new Errors();
        const wpsrPayload = { email: { required: 'Email required' } };
        wpsrErrors.record(wpsrPayload);

        wpsrErrors.clear();

        expect(wpsrErrors.errors).toEqual({});
        expect(wpsrErrors.errors).not.toBe(wpsrPayload);
        expect(wpsrErrors.has('email')).toBe(false);
        expect(wpsrPayload).toEqual({ email: { required: 'Email required' } });
    });
});
