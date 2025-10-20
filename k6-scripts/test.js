import http from "k6/http";
import { check, sleep } from "k6";
import { htmlReport } from "https://raw.githubusercontent.com/benc-uk/k6-reporter/main/dist/bundle.js";
import { textSummary } from "https://jslib.k6.io/k6-summary/0.0.1/index.js";

export let options = {
    stages: [
        { duration: "10s", target: 2 },
        { duration: "20s", target: 5 },
        { duration: "10s", target: 0 },
    ],
    thresholds: {
        http_req_duration: ["p(95)<600"],
        checks: ["rate>0.95"], // 95% of checks should pass
        http_req_failed: ["rate<0.05"], // Less than 5% failed requests
    },
};

export default function () {
    // Use admin kanban endpoint instead of heavy API endpoint
    const url = "http://crm/admin/leads/get?pipeline_id=1&limit=10&exclude_won_lost=false";

    const res = http.get(url, {
        headers: {
            Accept: "application/json",
            "X-API-KEY": `${__ENV.API_TOKEN || ""}`,
        },
    });

    check(res, {
        "status is 200": (r) => r.status === 200,
        "no redirects (302)": (r) => r.status !== 302,
        "no rate limiting (429)": (r) => r.status !== 429,
        "body not empty": (r) => r.body && r.body.length > 0,
    });

    sleep(2); // Increase sleep to reduce rate limiting
}

// 📊 Genereer HTML report na afloop van test
export function handleSummary(data) {
    return {
        "/scripts/report.html": htmlReport(data),
        stdout: textSummary(data, { indent: " ", enableColors: true }),
    };
}
