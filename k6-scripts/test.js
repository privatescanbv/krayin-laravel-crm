import http from "k6/http";
import { check, sleep } from "k6";
import { htmlReport } from "https://raw.githubusercontent.com/benc-uk/k6-reporter/main/dist/bundle.js";
import { textSummary } from "https://jslib.k6.io/k6-summary/0.0.1/index.js";

export let options = {
    stages: [
        { duration: "10s", target: 10 },
        { duration: "20s", target: 20 },
        { duration: "10s", target: 0 },
    ],
    thresholds: {
        http_req_duration: ["p(95)<500"],
    },
};

export default function () {
    const url =
        "http://crm/admin/leads/get?search=&searchFields=&pipeline_id=&limit=10&exclude_won_lost=false";

    const res = http.get(url, {
        headers: {
            Accept: "application/json",
            Authorization: `Bearer ${__ENV.API_TOKEN || ""}`,
        },
    });

    check(res, {
        "status is 200": (r) => r.status === 200,
        "body not empty": (r) => r.body && r.body.length > 0,
    });

    sleep(1);
}

// 📊 Genereer HTML report na afloop van test
export function handleSummary(data) {
    return {
        "/scripts/report.html": htmlReport(data),
        stdout: textSummary(data, { indent: " ", enableColors: true }),
    };
}
