require('dotenv').config();
const path = require('path');
const fs = require('fs');

const ACCOUNT_ARG = process.argv[2];
const BATCH_ARG = process.argv[3];
const PORT_ARG = process.argv[4];
const ACCOUNT_ID_ARG = process.argv[5];

if (!ACCOUNT_ARG || !BATCH_ARG || !PORT_ARG || !ACCOUNT_ID_ARG) {
    console.error('❌ Thiếu tham số vận hành!');
    process.exit(1);
}

let OUTPUT_DIR = path.join(__dirname, '..', 'web', 'plans');
const CONFIG_PATH = path.join(__dirname, 'config.json');
if (fs.existsSync(CONFIG_PATH)) {
    const configData = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
    OUTPUT_DIR = configData.OUTPUT_PLAN_DIR || OUTPUT_DIR;
}

const safeAccName = ACCOUNT_ARG.replace(/[^a-zA-Z0-9]/g, '_');

module.exports = {
    CHROME_PATH: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    CHROME_DATA_DIR: process.env.CHROME_DATA_DIR || path.join(__dirname, 'data-browser'),
    PHP_PATH: process.env.PHP_PATH || 'php',
    ACCOUNT_ARG,
    BATCH_ARG,
    PORT_ARG,
    ACCOUNT_ID_ARG,
    PLAN_FILE: path.join(OUTPUT_DIR, `schedule_plan_${safeAccName}_${BATCH_ARG}.csv`),
    PHP_DIR: path.join(__dirname, '..', 'web', 'core')
};