// Phase 8 load test for public publication pages (k6: https://k6.io).
//
//   k6 run -e BASE_URL=https://money-made-simple.staging.example.com -e ISSUE_SLUG=welcome tests/Load/public-pages.js
//
// Ramps to 200 virtual readers. Pages are served from the server-side page cache, so the
// thresholds below are the bar to hit before launch: 95% of requests under 500 ms, under 1% errors.
// Subscribing is rate limited per IP, so this script only reads pages.

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL;
const ISSUE_SLUG = __ENV.ISSUE_SLUG || '';

export const options = {
    stages: [
        { duration: '1m', target: 50 },
        { duration: '3m', target: 200 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<500'],
    },
};

export function setup() {
    if (!BASE_URL) {
        throw new Error('Set BASE_URL, for example -e BASE_URL=https://your-publication.staging.example.com');
    }
}

export default function () {
    const pages = ['/', '/archive', '/feed'];

    if (ISSUE_SLUG) {
        pages.push(`/issues/${ISSUE_SLUG}`);
    }

    for (const path of pages) {
        const response = http.get(`${BASE_URL}${path}`, { tags: { page: path } });
        check(response, { [`${path} is 200`]: (r) => r.status === 200 });
        sleep(Math.random() * 2 + 0.5);
    }
}
