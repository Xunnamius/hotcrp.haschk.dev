#!/usr/bin/env node

// @flow
const Koa = require('koa');
const Router = require('koa-router');
const base32Encode = require('base32-encode');
const hexToArrayBuffer = require('hex-to-array-buffer');
const DigitalOcean = require('do-wrapper').default;

require('dotenv').config();

const api = new DigitalOcean('e6c3d3bce6571c02293d6d1b863491ce9b77f13f663b77a90d5e1c861f52b81f', 1);
const app = new Koa();
const router = new Router();

const outputBuilder = (out, prefix = '') => console[prefix == 'ERR:' ? 'error' : 'log'](`hotcrpd:${prefix}`, out);
const outputErrorBuilder = out => outputBuilder(out, 'ERR:');

const {
    DEBUG,
    NODE_ENV,
    URN_PREFIX,
    BASE32_URN_LENGTH,
    APPLICATION_LABEL,
    HOTCRP_BACKEND_HOST,
    HOTCRP_BACKEND_PORT,
} = process.env;

const DEBUG_MODE = DEBUG || ['development', 'debug', 'test'].includes(NODE_ENV);

if(!URN_PREFIX)          throw new Error('Bad environment: missing URN_PREFIX');
if(!BASE32_URN_LENGTH)   throw new Error('Bad environment: missing BASE32_URN_LENGTH');
if(!APPLICATION_LABEL)   throw new Error('Bad environment: missing APPLICATION_LABEL');
if(!HOTCRP_BACKEND_HOST) throw new Error('Bad environment: missing HOTCRP_BACKEND_HOST');
if(!HOTCRP_BACKEND_PORT) throw new Error('Bad environment: missing HOTCRP_BACKEND_PORT');

router.get('primary', '/:contentHash', async ctx => {
    ctx.body = 'not ok';
    ctx.status = 400;

    const contentHash = (ctx.params.contentHash || '').toLowerCase()

    if(!contentHash) {
        outputBuilder(`(aborted request with empty or missing contentHash parameter)`);
        return;
    }

    if(contentHash.length != 64) {
        outputBuilder(`(aborted request with invalid contentHash "${contentHash}" of length ${contentHash.length} !== 64)`);
        return;
    }

    // ? Hash file data with proper algorithm
    const base32FileHash = base32Encode(hexToArrayBuffer(contentHash), 'Crockford', {
        padding: false
    });

    DEBUG_MODE && outputBuilder(`base32FileHash: ${base32FileHash}`);
    DEBUG_MODE && outputBuilder(`base32FileHash.length: ${base32FileHash.length}`);

    // ? Construct BASE32 encoded URN and slice it up to yield C1 and C2
    const base32Urn = base32Encode((new TextEncoder()).encode(`${URN_PREFIX}${base32FileHash}`), 'Crockford', {
        padding: false
    });

    DEBUG_MODE && outputBuilder(`base32Urn: ${base32Urn}`);
    DEBUG_MODE && outputBuilder(`base32Urn.length: ${base32Urn.length}`);

    if(base32Urn.length != BASE32_URN_LENGTH) {
        outputErrorBuilder(`(encountered strange base32Urn derived from "${contentHash}" of length ${base32Urn.length} != ${BASE32_URN_LENGTH})`);
        return;
    }

    ctx.body = 'ok';
    ctx.status = 200;

    const [ C1, C2 ] = [
        base32Urn.slice(0, base32Urn.length / 2),
        base32Urn.slice(base32Urn.length / 2, base32Urn.length),
    ];

    const recordName = `${C1}.${C2}.${APPLICATION_LABEL}`.toLowerCase();
    let res = '<no response object>';

    outputBuilder(`adding DNS TXT record to ${HOTCRP_BACKEND_HOST}:\ncontent hash ${ctx.params.contentHash} ==> TXT record ${recordName}`);

    try {
        await api.domains.getAllRecords(HOTCRP_BACKEND_HOST).then(async data => {
            const filtered = JSON.parse(data).domain_records.filter(item => item.type == 'TXT' && item.name == recordName);
            DEBUG_MODE && outputBuilder(`Filtered result: [ ${JSON.stringify(filtered)} ]`);

            if(filtered.length)
                outputBuilder('(skipping adding DNS TXT record as it already exists)');

            else {
                res = await api.domains.createRecord(HOTCRP_BACKEND_HOST, {
                    type: 'TXT',
                    name: recordName,
                    data: 'OK'
                });
            }
        });
    }

    catch(e) { outputErrorBuilder(e); }

    outputBuilder(DEBUG_MODE ? res : '<succeeded>');
});

app.use(router.routes());

const server = app.listen(HOTCRP_BACKEND_PORT).on('error', err => outputErrorBuilder(err));

outputBuilder(`hotcrp DNS adapter running in ${DEBUG_MODE ? 'debug' : 'production'} mode on ${HOTCRP_BACKEND_HOST}:${HOTCRP_BACKEND_PORT}`);

if(DEBUG_MODE) {
    outputBuilder(`URN_PREFIX: ${URN_PREFIX}`);
    outputBuilder(`BASE32_URN_LENGTH: ${BASE32_URN_LENGTH}`);
    outputBuilder(`APPLICATION_LABEL: ${APPLICATION_LABEL}`);
    outputBuilder(`HOTCRP_BACKEND_HOST: ${HOTCRP_BACKEND_HOST}`);
    outputBuilder(`HOTCRP_BACKEND_PORT: ${HOTCRP_BACKEND_PORT}\n`);
}

module.exports = server;
