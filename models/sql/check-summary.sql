SELECT form, outcome, SUM(total) AS total FROM {TABLE}
WHERE day >= CURDATE() - INTERVAL :days DAY
GROUP BY form, outcome ORDER BY form, outcome
