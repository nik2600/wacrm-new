import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, memo } from 'react';
import { createRoot } from 'react-dom/client';
import { html } from 'htm/react';
import { themeColor } from '../theme-colors.js';

export default function init() {
    // App base prefix (e.g. "/public" or "") derived from the current path so
    // every AJAX call + the post-save URL rewrite respect however the app is
    // mounted, instead of a hardcoded absolute "/flows".
    const APP_BASE = window.location.pathname.replace(/\/flows\/builder.*$/, '');

    const NTYPES = {
      trigger:   { label:'Trigger',         group:'Start',   bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M5 3l8 5-8 5z',                                                                                      desc:'Where the flow starts',  singleton:true, modes:['chat','call','instagram'] },
      message:   { label:'Send message',    group:'Send',    bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z',  desc:'Plain text message', modes:['chat','instagram'] },
      template:  { label:'Send template',   group:'Send',    bg:'#D9E5F2', fg:'#13478A', icon:'M2.5 2.5h11v11h-11zM2.5 6h11M6 13.5V6',                                                              desc:'Approved template' },
      media:     { label:'Send media',      group:'Send',    bg:'#FFF4E0', fg:'#7B5A14', icon:'M2 3h12v10H2zM6 7m-1 0a1 1 0 1 0 2 0 1 1 0 1 0-2 0M3 11l3-3 4 4 3-3 0 4',                            desc:'Image, video, doc, audio', modes:['chat','instagram'] },
      sequence:  { label:'Send sequence',   group:'Send',    bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M2 4h12M2 8h12M2 12h8M13 11l2 2-2 2',                                                            desc:'Stack text + media in one node' },
      buttons:   { label:'Quick replies',   group:'Send',    bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M2.5 2.5h11v5h-7l-3 3v-8Z M2.5 11h11 M2.5 13.5h11',                                                desc:'Tap-to-pick reply buttons', modes:['chat','instagram'] },
      list:      { label:'List message',    group:'Send',    bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M2.5 2.5h11v11h-11zM5 6h6M5 8h6M5 10h4',                                                            desc:'Interactive list picker' },
      ask:       { label:'Ask question',    group:'Listen',  bg:'#FBE9E7', fg:'#A1431F', icon:'M8 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12zM6 6.5a2 2 0 0 1 4 0c0 1.5-2 1.5-2 3M8 12.5h.01',                desc:'Save user reply to a var', modes:['chat','instagram'] },
      condition: { label:'Condition',       group:'Logic',   bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M8 2v3M5 8l3-3 3 3M8 5v9',                                                                          desc:'If / else branch', modes:['chat','call','instagram'] },
      delay:     { label:'Wait',            group:'Logic',   bg:'#FFF4E0', fg:'#7B5A14', icon:'M8 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12zM8 5v3l2 2',                                                    desc:'Pause for X minutes', modes:['chat','call','instagram'] },
      webhook:   { label:'Webhook',         group:'Logic',   bg:'#E8F5E9', fg:themeColor('wa-deep'), icon:'M3 8h3l1.5-4 2 8 1.5-4h2',                                                                          desc:'Call external HTTPS endpoint', modes:['chat','call','instagram'] },
      code:      { label:'Run code (JS)',    group:'Logic',   bg:'#1E2A33', fg:'#7CF3C4', icon:'M6 4L2.5 8 6 12M10 4l3.5 4L10 12',                                                                  desc:'Sandboxed JavaScript transform', modes:['chat','call'] /* NOT instagram: the JS sandbox is node:vm and the IG runner is PHP */ },
      mysql:     { label:'MySQL Query',     group:'Logic',   bg:'#E6F4EA', fg:'#137333', icon:'M3 4c0-1 2.2-1.8 5-1.8s5 .8 5 1.8v8c0 1-2.2 1.8-5 1.8s-5-.8-5-1.8zM3 4c0 1 2.2 1.8 5 1.8s5-.8 5-1.8M3 8c0 1 2.2 1.8 5 1.8s5-.8 5-1.8', desc:'Run a read-only SQL query' },
      ai:        { label:'AI assistant',    group:'AI',      bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M8 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM8 1v2M8 13v2M1 8h2M13 8h2M3.5 3.5l1.5 1.5M11 11l1.5 1.5M3.5 12.5l1.5-1.5M11 5l1.5-1.5', desc:'Reply with ChatGPT or Gemini', modes:['chat','instagram'] },
      tag:       { label:'Tag contact',     group:'Contact', bg:'#D9E5F2', fg:'#13478A', icon:'M3 3h6l5 5-6 6-5-5zM6 6m-1 0a1 1 0 1 0 2 0 1 1 0 1 0-2 0',                                          desc:'Add or remove a tag' },
      assign:    { label:'Assign agent',    group:'Contact', bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M6 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM2 14c0-3 2.5-5 4-5s4 2 4 5M11.5 3.5a2 2 0 1 0 0 4 2 2 0 0 0 0-4M10.5 10c1.9.2 3.5 1.7 3.5 4', desc:'Hand off to a human' },
      subflow:   { label:'Run sub-flow',    group:'Logic',   bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M3.5 8a1.8 1.8 0 1 0 0-0.01M12.5 3.5a1.8 1.8 0 1 0 0-0.01M12.5 12.5a1.8 1.8 0 1 0 0-0.01M5 7l6-3M5 9l6 3', desc:'Call another flow' },
      end:       { label:'End',             group:'Start',   bg:'#FBE9E7', fg:'#A1431F', icon:'M3 3h10v10H3z',                                                                                    desc:'Stop the flow here', modes:['chat','call','instagram'] },
      cta:        { label:'Call to action',  group:'Engage',  bg:'#FFE4D6', fg:'#A1431F', icon:'M3 8h7M7 5l3 3-3 3M11 3h2v10h-2',                                                                  desc:'Send a CTA button' },
      location:   { label:'Location',        group:'Engage',  bg:'#FFF4E0', fg:'#7B5A14', icon:'M8 1.5C5.5 1.5 3.5 3.4 3.5 5.7c0 3 4.5 8.8 4.5 8.8s4.5-5.8 4.5-8.8C12.5 3.4 10.5 1.5 8 1.5zM8 7a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z', desc:'Send a map pin' },
      poll:       { label:'Poll',            group:'Engage',  bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M3 13V7M7 13V3M11 13v-5',                                                                          desc:'Send a poll' },
      chatbot:    { label:'Chatbot',         group:'Engage',  bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M4 4h8v6H8l-2 2v-2H4zM6 7h.01M10 7h.01',                                                          desc:'Trigger another chatbot' },
      whatsapp_shop: { label:'WhatsApp Shop', group:'Commerce', bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M3 5h10l-1 7H4zM5 5V3.5a3 3 0 0 1 6 0V5',                                                          desc:'Open WhatsApp catalog' },
      woocommerce:{ label:'WooCommerce',    group:'Commerce', bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M2 4h12v6H2zM4 11l1 2M12 11l-1 2',                                                                  desc:'Send WooCommerce product' },
      shopify:    { label:'Shopify',         group:'Commerce', bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M5 4l-1 9 8 1V4l-2-1-2 1-3 0z',                                                                  desc:'Send Shopify product' },
      book_appointment: { label:'Book appointment', group:'Engage', bg:'#E8F0FE', fg:'#1A73E8', icon:'M3 4h10v9H3zM3 6h10M5 2v3M11 2v3M6 9h2M9 9h1',                                                  desc:'Pick a Google Calendar slot' },
      google_meet:      { label:'Google Meet',       group:'Engage', bg:'#E8F0FE', fg:'#1A73E8', icon:'M2 5h7v6H2zM10 6l3-1.5v7L10 10z',                                                              desc:'Create + send a Meet link' },
      wa_form:          { label:'WhatsApp form',     group:'Engage', bg:themeColor('wa-mint'), fg:themeColor('wa-deep'), icon:'M3 2h10v12H3zM5 5h6M5 8h6M5 11h4',                                                              desc:'Send a published Meta form' },
      // Google Workspace nodes — share the workspace's existing Google
      // OAuth (the one used for Calendar / Meet / BookAppointment).
      google_sheets:    { label:'Google Sheets',    group:'Integrations', bg:'#E6F4EA', fg:'#137333', icon:'M3 2h10v12H3zM3 5h10M3 8h10M3 11h10M6.5 5v9M9.5 5v9',                                       desc:'Write or read a row' },
      google_docs:      { label:'Google Docs',      group:'Integrations', bg:'#E8F0FE', fg:'#1A73E8', icon:'M3 2h7l3 3v9H3zM10 2v3h3M5 8h6M5 10h6M5 12h4',                                              desc:'Generate from a template' },
      google_form:      { label:'Google Form',      group:'Integrations', bg:'#F3E8FD', fg:'#7B2CBF', icon:'M3 2h10v12H3zM5 5h2M5 8h2M5 11h2M9 5h2M9 8h2M9 11h2',                                       desc:'Send link, capture answers' },
      // Sales Pipeline (CRM) — flow → deal. Lets a chatbot qualify a lead
      // and create/advance a deal on the /deals board.
      deal:             { label:'Create / move deal', group:'CRM',         bg:'#DFF1ED', fg:themeColor('wa-deep'), icon:'M2 12V8l6-5 6 5v4M2 12h12M6 12V9h4v3',                                                        desc:'Create or advance a CRM deal' },

      // ── CALL FLOW nodes (AI voice IVR — shown only when flow_type='call') ──
      cf_say:      { label:'Say',            group:'Voice',        bg:'#EAF6FF', fg:'#0B5FA5', icon:'M3 6h6l3-3v10l-3-3H3zM13 6.5a2 2 0 0 1 0 3',                                                        desc:'Speak a line (text-to-speech)',     modes:['call'] },
      cf_listen:   { label:'Listen',         group:'Voice',        bg:'#FBE9E7', fg:'#A1431F', icon:'M5 8a3 3 0 0 1 6 0v3a3 3 0 0 1-6 0zM3 8v3M13 8v3M8 14v1',                                            desc:'Capture caller speech → variable',  modes:['call'] },
      cf_ai:       { label:'AI Respond',     group:'Voice',        bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M8 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM8 1v2M8 13v2M1 8h2M13 8h2',                                       desc:'AI answers, spoken back',           modes:['call'] },
      cf_assistant:{ label:'AI Assistant',   group:'Voice',        bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M8 1.6a3 3 0 0 1 3 3V7a3 3 0 0 1-6 0V4.6a3 3 0 0 1 3-3zM3.5 7.5a4.5 4.5 0 0 0 9 0M8 12v2.4M5.5 14.4h5',    desc:'Answer with a voice agent',         modes:['call'] },
      cf_menu:     { label:'Menu / Branch',  group:'Call routing', bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M8 2v3M5 8l3-3 3 3M3 13h2M11 13h2M7 13h2',                                                          desc:'Route by intent / digit',           modes:['call'] },
      cf_search:   { label:'Search web',     group:'Call routing', bg:'#E8F5E9', fg:themeColor('wa-deep'), icon:'M7 2a5 5 0 1 0 0 10A5 5 0 0 0 7 2zM11 11l3 3',                                                       desc:'Look up the web → variable',        modes:['call'] },
      cf_transfer: { label:'Transfer',       group:'Call control', bg:'#FFF4E0', fg:'#7B5A14', icon:'M2 11V5a2 2 0 0 1 2-2h4M14 5v6a2 2 0 0 1-2 2H8M10 1l3 3-3 3M6 15l-3-3 3-3',                          desc:'Hand the call to a human',          modes:['call'] },
      cf_hangup:   { label:'Hang up',        group:'Call control', bg:'#FBE9E7', fg:'#A1431F', icon:'M3 9c3-3 7-3 10 0l-1.5 1.5-2-1V7.5a6 6 0 0 0-3 0V9l-2 1z',                                            desc:'End the call',                      modes:['call'], terminal:true },

      // ── INSTAGRAM nodes (AI DM automation — shown only when flow_type='instagram') ──
      ig_send_dm:      { label:'Send DM',          group:'Instagram', bg:'#FCE7F3', fg:'#9D174D', icon:'M2 4h12v8H6l-4 3z',                                              desc:'Send a direct message',           modes:['instagram'], hidden:true /* superseded by `message`, which now runs on Instagram too */ },
      ig_quick:        { label:'Quick replies',    group:'Instagram', bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M2 4h12v6H6l-2 2zM4.5 7h2M8 7h3.5',                              desc:'DM with tap-to-pick buttons',     modes:['instagram'], hidden:true /* superseded by `buttons`, which now runs on Instagram too */ },
      ig_buttons:      { label:'Button message',   group:'Instagram', bg:'#EAF6FF', fg:'#0B5FA5', icon:'M2 4h12v8H2zM4.5 9h3M9 9h2.5',                                   desc:'DM with URL / action buttons',    modes:['instagram'] },
      ig_reply_comment:{ label:'Reply to comment', group:'Instagram', bg:'#FFF4E0', fg:'#7B5A14', icon:'M2 5h12v7H9l-3 2v-2H2z',                                         desc:'Public reply under the comment',  modes:['instagram'] },
      ig_ai:           { label:'AI Reply',         group:'Instagram', bg:'#F3E9FF', fg:themeColor('accent-plum'), icon:'M8 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM8 1v2M8 13v2M1 8h2M13 8h2',   desc:'AI answers with your knowledge base', modes:['instagram'], hidden:true /* superseded by `ai`, which now runs on Instagram too */ },
      ig_products:     { label:'Send products',    group:'Instagram', bg:'#E8F5E9', fg:'#166534', icon:'M3 5.5h10l-1 7.5H4zM5.5 5.5V4a2.5 2.5 0 0 1 5 0v1.5', desc:'Shoppable carousel from your catalog', modes:['instagram'] },
      ig_ask:          { label:'Ask a question',   group:'Instagram', bg:'#EAF6FF', fg:'#0B5FA5', icon:'M6 5.5a2 2 0 1 1 2.6 1.9c-.6.2-.9.6-.9 1.2M8 11h.01', desc:'Ask, then save the reply to a variable', modes:['instagram'], hidden:true /* superseded by `ask`, which now runs on Instagram too */ },
      ig_lead:         { label:'Capture lead',      group:'Instagram', bg:'#FEF3C7', fg:'#92400E', icon:'M8 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM3 13c0-2.2 2.2-3.5 5-3.5s5 1.3 5 3.5', desc:'Save name / email / phone as a lead + deal', modes:['instagram'] },
    };

    // Which builder mode this canvas is in — 'chat' (WhatsApp), 'instagram'
    // (Instagram DM) or 'call' (AI voice IVR). Drives the palette and the
    // flow_type we persist.
    //
    // INITIAL_FLOW_TYPE is what the flow was CREATED/loaded as (?type= on the
    // builder URL). For messaging flows the operator switches channel from the
    // Trigger node itself, so the LIVE mode is derived from that node's
    // `channel` field via flowTypeOf(). The trigger node lives in React state,
    // so the palette re-filters the instant they change it — a module-level
    // `let` would not re-render.
    //
    // Call flows are a distinct builder (voice IVR) and never offer the switch.
    // Is the Instagram extension installed on THIS deployment? Set by the blade.
    // Everything Instagram in the builder hangs off this: the channel list, the
    // Trigger's channel choice, and the Instagram node group. Offering a channel
    // whose runtime is not installed produces a flow that saves and publishes
    // and then silently never fires — worse than not offering it.
    const IG_INSTALLED = document.getElementById('root')?.dataset?.extInstagram === '1';
    // IG accounts embedded in the page (data-ig-accounts) — the guaranteed source
    // for the trigger's account dropdown, immune to a stale-cached picker fetch.
    const IG_ACCOUNTS = (() => {
      try { return JSON.parse(document.getElementById('root')?.dataset?.igAccounts || '[]') || []; }
      catch (e) { return []; }
    })();

    const INITIAL_FLOW_TYPE = (() => {
      const t = document.getElementById('root')?.dataset?.flowType;
      if (t === 'call') return 'call';
      // An existing instagram flow opened after the extension was removed
      // falls back to chat rather than rendering a palette of dead nodes.
      if (t === 'instagram') return IG_INSTALLED ? 'instagram' : 'chat';
      return 'chat';
    })();
    const MSG_CHANNELS = IG_INSTALLED ? ['chat', 'instagram'] : ['chat'];
    const flowTypeOf = (list) => {
      if (INITIAL_FLOW_TYPE === 'call') return 'call';
      const trig = (Array.isArray(list) ? list : []).find(n => n && n.type === 'trigger');
      const c = trig?.data?.channel;
      return MSG_CHANNELS.includes(c) ? c : INITIAL_FLOW_TYPE;
    };
    const nodeInMode = (v, mode) => (Array.isArray(v.modes) ? v.modes : ['chat']).includes(mode);

    const Icon = ({ d, className='w-4 h-4' }) => html`<svg viewBox="0 0 16 16" className=${className} fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d=${d} /></svg>`;
    const nid = () => 'n_' + Math.random().toString(36).slice(2, 8);
    const eid = () => 'e_' + Math.random().toString(36).slice(2, 8);

    const NODE_W = 280;

    // Module-level cache of the workspace's approved templates.
    // FlowApp populates this once on mount via /templates/api/list,
    // then both <Inspector> and the canvas-side renderPreview look
    // up template details by name (header / body / footer / buttons /
    // carousel / attachment).
    let TEMPLATES_CACHE = [];
    const findTemplate = (nameOrId) => {
        if (!nameOrId) return null;
        return TEMPLATES_CACHE.find(t => t.template_name === nameOrId || String(t.id) === String(nameOrId)) || null;
    };
    // Quick-reply buttons of the template a 'template' node points at. ONLY
    // quick-reply buttons send a tap-back that can branch a flow — URL / call /
    // copy-code buttons don't reply, so they never become ports. Each returned
    // button becomes one branchable output port on the Send-template node.
    const templateQrButtons = (node) => {
        const d = (node && node.data) || {};
        const tt = findTemplate(d.tpl || d.tplId);
        const tb = (tt && Array.isArray(tt.buttons)) ? tt.buttons : [];
        return tb.filter(b => {
            const k = String((b && (b.type || b.button_type)) || '').toLowerCase();
            return !(k.includes('url') || k.includes('cta') || k.includes('visit') || k.includes('call') || k.includes('phone') || k.includes('copy'));
        });
    };

    // Module-level cache of the workspace's flows — drives the
    // Sub-flow node's dropdown. Refreshed on builder mount.
    let FLOWS_CACHE = [];

    // Admin-enabled AI text models (only providers with is_active=true
    // on admin_ai_keys). Drives the AI assistant node's Model picker.
    let AI_MODELS_CACHE = [];
    // Trained AI-Training chat assistants for the AI node's optional
    // knowledge-base attach. [{ id, name, status, sources }]
    let AI_ASSISTANTS_CACHE = [];
    // Voice (call) assistants — drives the Call Flow "AI Assistant" node picker.
    // [{ id, name }]
    let AI_CALL_ASSISTANTS_CACHE = [];

    // Workspace teams + their members — drives the Assign agent node.
    let TEAMS_CACHE = [];

    // Workspace contact tags — drives the Tag contact node's tag picker.
    let TAGS_CACHE = [];

    // Workspace AI agents — drives the Chatbot node's picker.
    let AGENTS_CACHE = [];
    let STAGES_CACHE = []; // Sales Pipeline stages for the deal_stage_changed trigger

    // Workspace commerce stores per provider — drives the commerce
    // nodes' store dropdown. Populated lazily per provider so we
    // don't fire 3 fetches at mount when 99% of flows don't use them.
    const COMMERCE_STORES = { whatsapp_shop: null, woocommerce: null, shopify: null };
    const COMMERCE_PRODUCTS_CACHE = {}; // key: `${provider}:${storeId}` → array

    // List-node option shape. Older flows saved options as bare strings;
    // the Sections build stores { title, description, section }. Coerce on
    // every read so a flow saved before this change keeps working (its
    // items land in one unnamed section with no descriptions).
    // Meta caps: title 24, description 72, section title 24.
    const LIST_TITLE_MAX = 24, LIST_DESC_MAX = 72, LIST_SECTION_MAX = 24;
    function listOpt(o, i) {
      if (typeof o === 'string') return { title: o, description: '', section: '' };
      return {
        title:       String(o?.title || ('Item ' + ((i || 0) + 1))),
        description: String(o?.description || ''),
        section:     String(o?.section || ''),
      };
    }

    function defaultData(type) {
      switch (type) {
        case 'trigger':   return {
          // kind: 'keyword' | 'tag_added' | 'group_join' | 'manual_enroll'
          // keyword     → matches inbound message text against `keywords`
          // tag_added   → fires when `tagId` attaches to a contact
          // group_join  → fires when contact joins `groupId`
          // manual_enroll → operator-only, no auto-fire
          kind:'keyword',
          // 'keywords' (match specific words) | 'any' (default route — runs
          // only when no keyword flow matched). Defaults to keywords so a new
          // flow never becomes an accidental catch-all.
          keywordMode:'keywords',
          keywords:'price, pricing, cost',
          tagId: '',      // tag_added picker
          groupId: '',    // group_join picker
          deviceId: '',   // optional pin for auto-enrolled flows; blank = workspace default
        };
        case 'message':   return { text:'Type your message…' };
        case 'template':  return { tpl:'' };
        case 'media':     return { kind:'image', filename:'', caption:'' };
        case 'sequence':  return { replies: [ { type:'text', text:'Hi {{name}}!' } ] };
        case 'buttons':   return { prompt:'Pick one', options:['Option A','Option B'], var:'choice' };
        case 'list':      return { prompt:'Pick from list', button:'View options', var:'choice', options:[
          { title:'Item 1', description:'', section:'' },
          { title:'Item 2', description:'', section:'' },
          { title:'Item 3', description:'', section:'' },
        ] };
        case 'ask':       return { prompt:"What's your name?", var:'answer', validate:'text', options: [] };
        case 'condition': return { conditions: [{ variable:'', operator:'equals', value:'' }], operators: [] };
        case 'delay':     return { amount:5, unit:'min' };
        case 'webhook':   return { method:'POST', url:'', body:'', contentType:'application/json', headers:[], save:'response' };
        case 'code':      return { code:'// Available variables:\n// - previousResponse : output of the previous node\n// - allResponses     : every saved {{variable}} so far\n// - functionArgs     : args passed in\n\nreturn {\n  ok: true,\n  message: "Hello from the code node"\n};', save:'result' };
        case 'mysql':     return { host:'localhost', port:'3306', database:'', username:'', password:'', sql:'SELECT * FROM products WHERE field = {{field}}', save:'rows' };
        case 'ai':        return { model:'gpt-4o-mini', prompt:'You are a friendly support assistant. Reply briefly.', save:'reply', assistant:'', extract:false, silent:false, fields:'' };
        // ── Call flow node defaults ──
        case 'cf_say':      return { text:'Hello! Thanks for calling.' };
        case 'cf_listen':   return { save:'caller_said', silenceTimeout:6 };
        case 'cf_ai':       return { model:'gpt-4o-mini', prompt:'You are a helpful phone assistant. Keep answers short and natural.', assistant:'', save:'ai_reply', endOnGoodbye:true };
        case 'cf_assistant': return { assistant:'', prompt:'', save:'assistant_reply', endOnGoodbye:true };
        case 'cf_menu':     return { mode:'intent', options:[ { match:'sales', label:'Sales' }, { match:'support', label:'Support' } ] };
        case 'cf_search':   return { query:'{{caller_said}}', save:'search', filler:'One moment, let me check that…' };
        case 'cf_transfer': return { number:'', message:'Connecting you to an agent now.' };
        case 'cf_hangup':   return { goodbye:'Thanks for calling. Goodbye!' };
        case 'ig_send_dm':       return { text:'Hi! Thanks for your message.' };
        case 'ig_quick':         return { text:'What can I help you with?', options:[ { title:'Pricing', payload:'PRICING' }, { title:'Support', payload:'SUPPORT' } ] };
        case 'ig_buttons':       return { text:'Tap a button below:', buttons:[ { type:'web_url', title:'Visit site', url:'https://' }, { type:'postback', title:'Talk to us', payload:'CONTACT' } ] };
        case 'ig_reply_comment': return { message:'Thanks! Just sent you a DM.' };
        case 'ig_ai':            return { model:'gpt-4o-mini', prompt:'You are our friendly Instagram assistant. Answer briefly and warmly.', assistant:'', save:'ai_reply' };
        case 'ig_products':      return { selection:'pick', productIds:[], limit:5, intro:'Here are some products you might like:', orderButton:true, orderLabel:'Order', detailsButton:true, detailsLabel:'View' };
        case 'ig_ask':           return { question:'What is your name?', save:'lead_name' };
        case 'ig_lead':          return { nameVar:'lead_name', emailVar:'lead_email', phoneVar:'lead_phone', notesVar:'', createDeal:true, ack:'Thanks! Our team will reach out shortly.' };
        case 'tag':       return { action:'add', tag:'', tagId:'' };
        case 'assign':    return { team:'', userId:'', message:'' };
        case 'subflow':   return { flow:'' };
        case 'end':       return {};
        case 'cta':       return { actions: [ { type:'url', label:'Visit website', value:'https://example.com' } ] };
        case 'location':  return { lat:'', lng:'', address:'Office address', title:'Our office' };
        case 'poll':      return { question:'Pick one', options:['Option A','Option B'], multi:false };
        case 'chatbot':   return { bot:'' };
        case 'whatsapp_shop':
        case 'woocommerce':
        case 'shopify':       return { storeId:'', productItems:[], headerText:'', bodyText:'Tap a product to see details:', footerText:'', abandonedWaitMinutes:5 };
        case 'book_appointment': return {
          slotCount: 5,                            // how many slots to offer
          prompt: 'Pick a time that works for you:',
          confirmation: 'Booked! See you on {{slot}}.',
          collectEmail: false,                     // ask customer for email (enables Google invite)
          calendarOverride: '',                    // optional — defaults to workspace setting
        };
        case 'google_meet': return {
          title: 'WhatsApp consultation with {{name}}',
          durationMinutes: 30,
          leadMinutes: 5,
          sendCalendarInvite: false,
          messageTemplate: 'Your meeting link:\n{{meet_link}}\n\nStarts {{meet_start}}',
        };
        case 'wa_form': return {
          formId: '',
          bodyText: 'Please tap below to fill out our quick form.',
          ctaLabel: 'Open form',
          flowVariable: 'form_submission',
        };
        case 'google_sheets': return {
          mode: 'write',                        // write | read
          sheetId: '',                          // Drive file id
          tabName: '',                          // blank = Sheet1 default
          columns: [                            // write mode
            { header: 'Name',  value: '{{name}}' },
            { header: 'Phone', value: '{{phone}}' },
          ],
          matchByHeader: false,                 // write mode — off = append by
                                                // position (default); on = write
                                                // each value under its named column
          matchColumn: '',                      // read mode
          matchValue: '',
          saveAs: 'sheet_row',                  // read mode — flow var
        };
        case 'google_docs': return {
          templateId: '',                       // Drive file id of template
          newTitle: 'Document for {{name}}',
          placeholders: [
            { key: 'customer_name', value: '{{name}}' },
          ],
          shareable: true,
          messageTemplate: "Here's your document:\n{{doc_url}}",
          saveAs: 'doc_url',
        };
        case 'google_form': return {
          formId: '',                           // Google Form id
          bodyText: 'Please fill out this short form:',
          saveAs: 'google_form',
          expiresInSec: 86400,                  // 24h default
        };
        case 'deal': return {
          action: 'create',                     // create | move
          dealName: '{{contact_name}} — deal',
          stageId: '',                          // pipeline stage (stage knows its pipeline)
          value: '',                            // optional, may carry {{vars}}
          ownerId: '',                          // optional assignee user id
          saveAs: 'deal_id',                    // flow var holding the new/moved deal id
        };
      }
      return {};
    }

    function nodeRows(node) {
      const t = node.type, d = node.data || {};
      switch (t) {
        case 'trigger':   return [{ id:'out', label: ({
          'keyword':            'on keyword',
          'tag_added':          'on tag added',
          'group_join':         'on group join',
          'contact_created':    'on new contact',
          'opt_in':             'on opt-in',
          'order_placed':       'on order',
          'deal_stage_changed': 'on deal stage',
          'away':               'while away',
          'out_of_hours':       'after hours',
          'manual_enroll':      'on enroll',
        }[d.kind] || d.kind || 'manual'), kind:'flow' }];
        case 'message':   return [{ id:'out', label:'next', kind:'flow' }];
        case 'template': {
          // One output port per quick-reply button so each can wire to its
          // own next node (like the Buttons node). No quick-reply buttons →
          // a single "next" port (a plain template send).
          const qr = templateQrButtons(node);
          if (!qr.length) return [{ id:'out', label:'next', kind:'flow' }];
          return qr.map((b, i) => ({ id:'p'+i, label:(b.text || b.title || b.label || ('Button '+(i+1))), kind:'flow' }));
        }
        case 'media':     return [{ id:'out', label:'next', kind:'flow' }];
        case 'sequence':  return [{ id:'out', label:'next', kind:'flow' }];
        case 'buttons':   return ((d.options||[]).length ? d.options : ['Option']).map((o,i)=>({ id:'p'+i, label:o, kind:'flow' }));
        case 'list':      return ((d.options||[]).length ? d.options : ['Item']).map((o,i)=>({ id:'p'+i, label:listOpt(o,i).title, kind:'flow' }));
        case 'ask': {
          // Free-text answers route through a single port unless the
          // operator defined expected answers — then each becomes a
          // branch port (like buttons) plus an "else" catch-all.
          const exp = Array.isArray(d.options) ? d.options : [];
          if (exp.length === 0) return [{ id:'out', label:'reply received', kind:'flow' }];
          return exp.map((o, i) => ({ id:'p'+i, label: o || ('Answer '+(i+1)), kind:'flow' }))
                    .concat([{ id:'else', label:'else / no match', kind:'no' }]);
        }
        case 'condition': return [{ id:'yes', label:'IF · true', kind:'yes' }, { id:'no', label:'ELSE · false', kind:'no' }];
        case 'delay':     return [{ id:'out', label:'after wait', kind:'flow' }];
        case 'webhook':   return [{ id:'out', label:'response', kind:'flow' }];
        case 'code':      return [{ id:'out', label:'done', kind:'flow' }];
        case 'cf_say':      return [{ id:'out', label:'spoken', kind:'flow' }];
        case 'cf_listen':   return [{ id:'out', label:'heard', kind:'flow' }];
        case 'cf_ai':       return [{ id:'out', label:'answered', kind:'flow' }];
        case 'cf_assistant': return [{ id:'out', label:'answered', kind:'flow' }];
        case 'cf_search':   return [{ id:'out', label:'results', kind:'flow' }];
        case 'cf_transfer': return [{ id:'out', label:'if no answer', kind:'flow' }];
        case 'cf_hangup':   return [];
        case 'ig_send_dm':       return [{ id:'out', label:'sent', kind:'flow' }];
        case 'ig_buttons':       return [{ id:'out', label:'sent', kind:'flow' }];
        case 'ig_reply_comment': return [{ id:'out', label:'replied', kind:'flow' }];
        case 'ig_ai':            return [{ id:'out', label:'answered', kind:'flow' }];
        case 'ig_products':      return [{ id:'out', label:'sent', kind:'flow' }];
        case 'ig_ask':           return [{ id:'out', label:'answered', kind:'flow' }];
        case 'ig_lead':          return [{ id:'out', label:'saved', kind:'flow' }];
        case 'ig_quick':    return ((d.options||[]).length ? d.options : [{title:'option'}])
                                .map((o,i)=>({ id:'p'+i, label:(o.title||('Option '+(i+1))), kind:'flow' }));
        case 'cf_menu':     return ((d.options||[]).length ? d.options : [{match:'option'}])
                                .map((o,i)=>({ id:'p'+i, label:(o.label||o.match||('Option '+(i+1))), kind:'flow' }))
                                .concat([{ id:'nomatch', label:'no match', kind:'no' }]);
        case 'mysql':     return [{ id:'out', label:'rows', kind:'flow' }];
        case 'ai':        return [{ id:'out', label:'reply ready', kind:'flow' }];
        case 'tag':       return [{ id:'out', label:'next', kind:'flow' }];
        case 'assign':    return [{ id:'out', label:'next', kind:'flow' }];
        case 'subflow':   return [{ id:'out', label:'after run', kind:'flow' }];
        case 'end':       return [];
        case 'cta':
        case 'location':
        case 'chatbot':   return [{ id:'out', label:'next', kind:'flow' }];
        case 'whatsapp_shop':
        case 'woocommerce':
        case 'shopify':   return [
          { id:'purchased', label:'purchased', kind:'yes' },
          { id:'abandoned', label:'abandoned', kind:'no'  },
        ];
        case 'book_appointment': return [
          { id:'booked',   label:'booked',   kind:'yes' },
          { id:'no_slots', label:'no slots', kind:'no' },
        ];
        case 'google_meet': return [{ id:'out', label:'link sent', kind:'flow' }];
        case 'wa_form':     return [{ id:'out', label:'submitted', kind:'flow' }];
        case 'google_sheets': return [{ id:'out', label: (node?.data?.mode === 'read' ? 'row loaded' : 'row written'), kind:'flow' }];
        case 'google_docs':   return [{ id:'out', label:'doc sent', kind:'flow' }];
        case 'google_form':   return [
          { id:'submitted', label:'submitted', kind:'yes' },
          { id:'timeout',   label:'timed out', kind:'no'  },
        ];
        case 'deal':      return [
          { id:'created', label: (node?.data?.action === 'move' ? 'moved' : 'created'), kind:'yes' },
          { id:'error',   label:'error', kind:'no' },
        ];
        case 'poll':      return ((d.options||[]).length ? d.options : ['Option']).map((o,i)=>({ id:'p'+i, label:o, kind:'flow' }));
      }
      return [];
    }

    const bezierPath = (x1, y1, x2, y2) => {
      const dx = Math.max(40, Math.abs(x2 - x1) * 0.5);
      return `M ${x1} ${y1} C ${x1+dx} ${y1}, ${x2-dx} ${y2}, ${x2} ${y2}`;
    };

    function Toolbar({ flowId, flowName, setFlowName, status, onSave, onPublish, onTest, onVars, onUndo, onRedo, canUndo, canRedo, onAIGen }) {
      const isPublished = status === 'Published';
      const isUnsaved = status.includes('unsaved');
      const statusLabel = isPublished ? 'Published' : isUnsaved ? 'Unsaved' : 'Draft';
      const dotColor = isPublished ? themeColor('wa-green') : isUnsaved ? themeColor('accent-coral') : '#92A29E';
      return html`
        <header className="flow-toolbar h-14 bg-paper-0 border-b border-paper-200 flex items-center px-4 gap-2 shrink-0 z-30 relative">
          <a href="/flows" className="w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center" title="Back to flows">
            <${Icon} d="M10 4l-4 4 4 4" className="w-3.5 h-3.5" />
          </a>
          <div className="flex items-center gap-2.5 pl-1 pr-1 py-1 min-w-0">
            <span className="w-9 h-9 rounded-md bg-wa-deep text-paper-0 grid place-items-center">
              <svg viewBox="0 0 16 16" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.7"><circle cx="3.5" cy="8" r="1.6"/><circle cx="12.5" cy="3.5" r="1.6"/><circle cx="12.5" cy="12.5" r="1.6"/><path d="M5 7l6-3M5 9l6 3"/></svg>
            </span>
            <div className="min-w-0">
              <div className="flow-eyebrow font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 leading-none">Flow Builder</div>
              <input value=${flowName} onChange=${e => setFlowName(e.target.value)} className="font-serif text-[16px] leading-tight tracking-[-0.01em] bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0 -ml-px w-[220px]" />
            </div>
          </div>
          <div className="flex-1"></div>
          <button onClick=${onAIGen} title="Generate with AI" className="btn-ai-shine relative isolate overflow-hidden px-3 py-1.5 rounded-full text-[11.5px] font-semibold inline-flex items-center gap-1.5">
            <${Icon} d="M8 1v3M8 12v3M1 8h3M12 8h3M3.5 3.5l2 2M10.5 10.5l2 2M3.5 12.5l2-2M10.5 5.5l2-2" className="w-3 h-3" />
            <span className="flow-ai-text">Generate with AI</span>
          </button>
          <button onClick=${onTest} className="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5"><${Icon} d="M5 3l8 5-8 5z" className="w-3 h-3" />Test</button>
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-paper-50 border border-paper-200 text-[10.5px] font-medium text-ink-700" title=${statusLabel}>
            <span className="w-1.5 h-1.5 rounded-full" style=${{ background: dotColor }}></span>
            ${statusLabel}
          </span>
          <button onClick=${onSave} className="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">${flowId ? 'Save changes' : 'Save'}</button>
          <button onClick=${onPublish} className="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5"><${Icon} d="M2 8l5 5 7-9" className="w-3 h-3" />Publish</button>
        </header>
      `;
    }

    function Palette({ onAddCenter, onSearch, query, open, mode }) {
      // `mode` MUST be in the dep array — it changes when the operator flips the
      // Trigger node's channel, and without it the palette would keep showing
      // the previous channel's nodes.
      const groups = useMemo(() => {
        const out = {};
        Object.entries(NTYPES).forEach(([k,v]) => {
          if (v.hidden) return; // legacy types still rendered on existing flows but not addable
          if (!nodeInMode(v, mode)) return; // chat flows hide call nodes and vice-versa
          const q = query.toLowerCase();
          if (q && !v.label.toLowerCase().includes(q) && !v.desc.toLowerCase().includes(q) && !v.group.toLowerCase().includes(q)) return;
          (out[v.group] = out[v.group] || []).push([k,v]);
        });
        return out;
      }, [query, mode]);
      const onDragStart = (e, type) => { e.dataTransfer.setData('node-type', type); e.dataTransfer.effectAllowed = 'copy'; };
      if (!open) return null;
      return html`
        <aside className="flow-palette w-[260px] bg-paper-0 border-r border-paper-200 flex flex-col shrink-0 z-20 relative">
          <div className="px-4 pt-4 pb-3 border-b border-paper-200">
            <div className="flex items-center justify-between mb-2">
              <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                Add a node
                ${/* Name the channel outright — the palette silently changes
                      contents when the Trigger's channel changes, and without
                      a label there was no way to tell which set you're seeing. */
                  mode === 'instagram'
                    ? html`<span className="ml-1.5 normal-case tracking-normal text-[10px] font-sans px-1.5 py-0.5 rounded ig-grad-soft text-white">Instagram</span>`
                    : mode === 'call'
                      ? html`<span className="ml-1.5 normal-case tracking-normal text-[10px] font-sans px-1.5 py-0.5 rounded bg-paper-100 text-ink-700">Call</span>`
                      : html`<span className="ml-1.5 normal-case tracking-normal text-[10px] font-sans px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep">WhatsApp</span>`}
              </div>
              <span className="text-[9.5px] font-mono text-ink-500">${Object.values(groups).reduce((a,b)=>a+b.length,0)}</span>
            </div>
            <div className="relative">
              <svg viewBox="0 0 16 16" className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" strokeWidth="1.6"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
              <input value=${query} onChange=${e => onSearch(e.target.value)} placeholder="Search nodes…" className="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-full bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            </div>
          </div>
          <div className="flex-1 overflow-y-auto px-3 py-3 space-y-4">
            ${Object.entries(groups).map(([g, items]) => html`
              <div key=${g}>
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5 px-1">${g}</div>
                ${items.map(([k,v]) => html`
                  <div key=${k} draggable=${true} onDragStart=${e => onDragStart(e, k)} onDoubleClick=${() => onAddCenter(k)}
                       className="pal-card flex items-center gap-2.5 px-2.5 py-2 rounded-xl border border-paper-200 bg-paper-0 transition mb-1.5 cursor-grab active:cursor-grabbing">
                    <span className="chip-icon" style=${{ background: v.bg, color: v.fg }}>
                      <${Icon} d=${v.icon} className="w-4 h-4" />
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="text-[12.5px] font-semibold leading-tight text-ink-900">${v.label}</div>
                      <div className="text-[10.5px] text-ink-500 leading-snug truncate">${v.desc}</div>
                    </div>
                  </div>`)}
              </div>`)}
            ${Object.keys(groups).length === 0 ? html`<div className="text-center text-[12px] text-ink-500 py-6">No nodes match.</div>` : null}
          </div>
          <div className="px-4 py-2.5 border-t border-paper-200 text-[10.5px] text-ink-500 leading-snug bg-paper-50/40">
            <span className="font-semibold text-ink-700">Tip ·</span> drag onto canvas, or double-click to add at center.
          </div>
        </aside>
      `;
    }

    function renderPreview(node) {
      const d = node.data || {};
      switch (node.type) {
        case 'trigger':
          return html`<div className="px-3 pt-2 pb-2 text-[11.5px] text-ink-700">
            <span className="text-ink-500">When </span><span className="font-mono">${d.kind || 'manual'}</span>
            ${d.kind === 'keyword' ? html`<div className="font-mono text-[11px] text-ink-900 mt-0.5 truncate">${d.keywordMode === 'any' ? '— any message —' : (d.keywords || '⚠ no keywords set')}</div>` : null}
          </div>`;
        case 'message':
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble wa whitespace-pre-wrap break-words">${(d.text || 'Type a message…').slice(0,140)}${(d.text||'').length>140?'…':''}</div></div>`;
        case 'template': {
          const t = findTemplate(d.tpl);
          const bodyLine = t ? (t.template_body || '').split('\n')[0] : 'pick a template…';
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble">
            <div className="font-mono text-[10.5px] text-ink-500 mb-0.5">${d.tpl || 'pick template'}</div>
            ${t && t.header ? html`<div className="font-semibold text-[11.5px]">${t.header}</div>` : null}
            <div className="truncate">${bodyLine}</div>
            ${t && Array.isArray(t.buttons) && t.buttons.length ? html`<div className="text-[10px] text-ink-500 mt-1">${t.buttons.length} button(s)</div>` : null}
          </div></div>`;
        }
        case 'media': {
          // SVG icon per media kind (per house style — no emojis).
          const kind = d.kind || 'image';
          const iconPaths = {
            image:    'M2 3h12v10H2zM6 7m-1 0a1 1 0 1 0 2 0 1 1 0 1 0-2 0M3 11l3-3 4 4 3-3 0 4',
            video:    'M2 3h12v10H2zM6 5l5 3-5 3z',
            document: 'M4 2h6l3 3v9H4zM10 2v3h3',
            audio:    'M5 4v8M5 6c-2 0-2 4 0 4M5 6c2 0 2 4 0 4M10 3v10M10 5c-2 0-2 6 0 6M10 5c2 0 2 6 0 6',
          };
          const hasFile = !!(d.url || (d.filename && d.filename.trim()));
          const label = d.filename || (d.url ? d.url.split('/').pop() : null) || 'no file selected';
          // Image with URL → thumbnail tile. Other types → name + sub-label.
          if (kind === 'image' && d.url) {
            return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble wa">
              <img src=${d.url} alt="" style=${{ display:'block', maxWidth:'100%', maxHeight:'120px', objectFit:'cover', borderRadius:'4px' }} />
              ${d.caption ? html`<div className="mt-1 text-[12px]">${d.caption.slice(0,80)}${d.caption.length>80?'…':''}</div>` : null}
            </div></div>`;
          }
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble wa flex items-center gap-2">
            <span className="w-6 h-6 rounded grid place-items-center shrink-0" style=${{ background: hasFile ? '#D9FDD3' : themeColor('paper-50'), color: hasFile ? themeColor('wa-deep') : '#92A29E' }}>
              <svg viewBox="0 0 16 16" className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.6"><path d=${iconPaths[kind] || iconPaths.document} /></svg>
            </span>
            <div className="min-w-0">
              <div className=${'font-medium truncate ' + (hasFile ? '' : 'text-ink-500 italic')}>${label}</div>
              <div className="text-[10.5px] text-ink-500">${kind}${d.caption ? ' · ' + d.caption.slice(0,30) : ''}</div>
            </div>
          </div></div>`;
        }
        case 'sequence': {
          const replies = Array.isArray(d.replies) ? d.replies : [];
          if (replies.length === 0) {
            return html`<div className="px-3 pt-2 pb-2 text-[11.5px] text-ink-500 italic">No replies yet — open inspector to add</div>`;
          }
          const chipFor = (r) => {
            const t = r.type || 'text';
            if (t === 'text') return { label: (r.text || '').slice(0, 38) || 'empty text', cls: 'bg-wa-bubble text-wa-deep' };
            if (t === 'image') return { label: r.filename || (r.url ? r.url.split('/').pop() : 'image') , cls: 'bg-[#FFF4E0] text-[#7B5A14]' };
            if (t === 'video') return { label: r.filename || 'video', cls: 'bg-[#F3E9FF] text-[#5B3D8A]' };
            if (t === 'audio') return { label: r.filename || 'audio', cls: 'bg-[#FBE9E7] text-[#A1431F]' };
            if (t === 'document') return { label: r.filename || 'document', cls: 'bg-[#D9E5F2] text-[#13478A]' };
            return { label: t, cls: 'bg-paper-100 text-ink-700' };
          };
          return html`<div className="px-3 pt-2 pb-2 space-y-1">
            ${replies.slice(0, 5).map((r, i) => {
              const c = chipFor(r);
              return html`<div key=${i} className=${'flex items-center gap-1.5 px-2 py-1 rounded ' + c.cls}>
                <span className="font-mono text-[9.5px] opacity-70">${i + 1}.</span>
                <span className="text-[11px] truncate flex-1">${c.label || (r.type || 'reply')}</span>
                <span className="font-mono text-[9.5px] uppercase opacity-70">${r.type || 'text'}</span>
              </div>`;
            })}
            ${replies.length > 5 ? html`<div className="text-[10.5px] text-ink-500 italic px-2">+ ${replies.length - 5} more…</div>` : null}
          </div>`;
        }
        case 'buttons':
          return html`<div className="px-3 pt-2.5 pb-1"><div className="bubble wa">${d.prompt || 'Prompt…'}</div></div>`;
        case 'list':
          // Canvas card mini-preview: bubble + opener button (no items
          // listed inline — they only appear after the customer taps).
          return html`<div className="px-3 pt-2.5 pb-2">
            <div className="bubble wa">${d.prompt || 'Prompt…'}</div>
            <div className="mt-1 text-[11px] text-wa-deep border border-paper-200 rounded px-2 py-1 inline-flex items-center gap-1 bg-white">
              <svg viewBox="0 0 16 16" className="w-3 h-3" fill="none" stroke="currentColor" strokeWidth="1.7"><path d="M2.5 4h11M2.5 8h11M2.5 12h11"/></svg>
              <span className="font-semibold">${d.button || 'View options'}</span>
            </div>
            ${(d.options || []).length ? html`<div className="text-[10px] text-ink-500 mt-1">${(d.options||[]).length} item(s) — shown after tap</div>` : null}
          </div>`;
        case 'ask': {
          const exp = Array.isArray(d.options) ? d.options : [];
          return html`<div className=${'px-3 pt-2.5 ' + (exp.length ? 'pb-1' : 'pb-2')}>
            <div className="bubble wa">${d.prompt || "What's your name?"}</div>
            ${exp.length === 0 ? html`<div className="text-[10.5px] text-ink-500 mt-1.5">customer's reply → next node</div>` : null}
          </div>`;
        }
        case 'condition': {
          const conds = Array.isArray(d.conditions) ? d.conditions : [];
          const ops   = Array.isArray(d.operators)  ? d.operators  : [];
          if (conds.length === 0) {
            return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px] text-ink-500 italic">No conditions set</div>`;
          }
          const opLabel = (op) => ({equals:'=', not_equals:'≠', contains:'⊆', not_contains:'⊄', gt:'>', lt:'<', exists:'is set'})[op] || op;
          return html`<div className="px-3 pt-2 pb-1.5 text-[11px] text-ink-700 leading-snug">
            <span className="font-mono text-ink-500 text-[10px]">IF </span>
            ${conds.map((c, i) => html`
              ${i > 0 ? html`<span className="font-mono text-[10px] mx-1 px-1 py-0.5 rounded ${(ops[i-1] || 'AND') === 'OR' ? 'bg-accent-amber/15 text-[#7B5A14]' : 'bg-wa-bubble text-wa-deep'}">${ops[i-1] || 'AND'}</span>` : null}
              <span className="font-mono text-ink-900">{{${c.variable || '?'}}}</span>
              <span className="text-ink-500 mx-1">${opLabel(c.operator || 'equals')}</span>
              <span className="font-mono text-ink-900">${c.value || '?'}</span>
            `)}
          </div>`;
        }
        case 'delay':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px] text-ink-700"><span className="text-ink-500">Wait </span><span className="font-mono text-ink-900 text-[13px]">${d.amount ?? 5} ${d.unit || 'min'}</span></div>`;
        case 'webhook':
          return html`<div className="px-3 pt-2 pb-1.5 flex items-center gap-2 text-[11.5px]">
            <span className="font-mono text-[10px] uppercase px-1.5 py-0.5 rounded bg-paper-50 text-ink-700 border border-paper-200">${d.method || 'POST'}</span>
            <span className="font-mono text-[11px] truncate flex-1">${(d.url || 'https://…').slice(0,32)}</span>
          </div>`;
        case 'code':
          return html`<div className="px-3 pt-2 pb-1.5 flex items-center gap-2 text-[11.5px]">
            <span className="font-mono text-[10px] uppercase px-1.5 py-0.5 rounded bg-paper-50 text-ink-700 border border-paper-200">JS</span>
            <span className="text-ink-500">save →</span><span className="font-mono text-[11px] text-ink-900">{{${d.save || 'result'}}}</span>
          </div>`;
        case 'ai':
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble">🤖 ${d.model || 'gpt-4o-mini'}</div>
            <div className="text-[10.5px] text-ink-500 mt-1.5 line-clamp-2">${d.prompt || 'You are a friendly support assistant…'}</div></div>`;
        case 'tag': {
          // Prefer the linked tag's CURRENT name from the workspace
          // cache (so renames reflect on the canvas) and fall back to
          // the snapshot string when the tag was typed as new.
          const cached = TAGS_CACHE.find(t => String(t.id) === String(d.tagId));
          const label  = cached ? cached.name : (d.tag || '—');
          const swatch = cached?.color || themeColor('wa-deep');
          return html`<div className="px-3 pt-2 pb-1.5 flex items-center gap-2 text-[11.5px]">
            <span className="text-ink-500">${d.action || 'add'}</span>
            <span className="text-[11px] px-2 py-0.5 rounded-full bg-wa-mint border" style=${{ borderColor: swatch + '66', color: swatch }}>
              <span className="inline-block w-1.5 h-1.5 rounded-full mr-1" style=${{ background: swatch }}></span>${label}
            </span>
            ${!cached && d.tag ? html`<span className="text-[10px] text-ink-500 italic">new</span>` : null}
          </div>`;
        }
        case 'assign': {
          const team = TEAMS_CACHE.find(t => String(t.id) === String(d.team));
          const teamLabel = team ? team.name : (d.team ? ('Team #' + d.team) : '— pick team —');
          const agentLabel = d.userId && team && Array.isArray(team.members)
            ? (team.members.find(m => String(m.id) === String(d.userId))?.name || 'agent #' + d.userId)
            : null;
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]">
            <div className="flex items-center gap-2"><span className="text-ink-500">Team</span><span className="font-mono text-ink-900 truncate">${teamLabel}</span></div>
            ${agentLabel ? html`<div className="flex items-center gap-2 mt-0.5"><span className="text-ink-500">Agent</span><span className="font-mono text-ink-900 truncate">${agentLabel}</span></div>` : null}
            ${d.message ? html`<div className="text-[10.5px] text-ink-500 mt-1 italic truncate">"${d.message.slice(0,40)}"</div>` : null}
          </div>`;
        }
        case 'subflow':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">▶ run </span><span className="font-mono text-ink-900">${d.flow || 'pick flow…'}</span></div>`;
        case 'cta': {
          // New multi-action shape. Backwards-compat with the old
          // single-kind shape: if `actions[]` is missing, synthesize
          // one from the legacy `kind/label/url/phone/code` fields.
          let actions = Array.isArray(d.actions) ? d.actions : null;
          if (!actions) {
            const k = d.kind || 'url';
            const v = k === 'phone' || k === 'call_now' ? (d.phone || '') : k === 'copy' ? (d.code || '') : (d.url || '');
            actions = [{ type: k, label: d.label || 'CTA', value: v }];
          }
          const iconFor = (t) => t === 'phone' || t === 'call_now'
              ? 'M3 3h3l1 3-1.5 1a8 8 0 0 0 4 4l1-1.5 3 1v3a11 11 0 0 1-10.5-10.5z'
              : t === 'copy'  ? 'M5 5h7v8H5z M3 3h7v2'
              :                 'M5 5l6 6 M7 5h4v4';
          return html`<div className="px-3 pt-2 pb-2 space-y-1">
            ${actions.slice(0, 3).map((a, i) => html`
              <div key=${'act-'+i} className="flex items-center gap-1.5 px-2 py-1 rounded bg-wa-bubble text-wa-deep text-[11px]">
                <svg viewBox="0 0 16 16" className="w-3 h-3 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.6"><path d=${iconFor(a.type || 'url')}/></svg>
                <span className="font-medium truncate flex-1">${a.label || (a.type === 'copy' ? 'Copy' : a.type === 'phone' || a.type === 'call_now' ? 'Call' : 'Visit')}</span>
                <span className="font-mono text-[9.5px] opacity-70 uppercase">${a.type || 'url'}</span>
              </div>
            `)}
          </div>`;
        }
        case 'location':
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble">
            <div className="flex items-center gap-1.5">
              <svg viewBox="0 0 16 16" className="w-3 h-3 text-accent-coral shrink-0" fill="currentColor"><path d="M8 1.5C5.5 1.5 3.5 3.4 3.5 5.7c0 3 4.5 8.8 4.5 8.8s4.5-5.8 4.5-8.8C12.5 3.4 10.5 1.5 8 1.5zM8 7a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>
              <span className="font-medium">${d.title || 'Location'}</span>
            </div>
            <div className="text-[10.5px] text-ink-500 truncate">${d.address || '—'}</div>
          </div></div>`;
        case 'poll':
          return html`<div className="px-3 pt-2.5 pb-1"><div className="bubble">📊 ${d.question || 'Poll question'}</div></div>`;
        case 'chatbot': {
          const agent = AGENTS_CACHE.find(a => String(a.id) === String(d.bot));
          const label = agent ? agent.name : (d.bot ? ('Agent #' + d.bot) : 'pick agent…');
          const sub = agent && agent.model ? agent.model : null;
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]">
            <div className="flex items-center gap-1.5">
              <svg viewBox="0 0 16 16" className="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M4 4h8v6H8l-2 2v-2H4z"/></svg>
              <span className="font-mono text-ink-900 truncate">${label}</span>
            </div>
            ${sub ? html`<div className="text-[10px] text-ink-500 mt-0.5 truncate">${sub}</div>` : null}
          </div>`;
        }
        case 'whatsapp_shop':
        case 'woocommerce':
        case 'shopify': {
          const items = Array.isArray(d.productItems) ? d.productItems : [];
          if (items.length === 0) {
            return html`<div className="px-3 pt-2 pb-2 text-[11.5px] text-ink-500 italic">No products picked — open inspector</div>`;
          }
          return html`<div className="px-3 pt-2 pb-2">
            <div className="flex items-center gap-1.5 mb-1.5">
              ${items.slice(0, 3).map((p, i) => p.image
                ? html`<img key=${'p-'+i} src=${p.image} alt="" className="w-9 h-9 rounded border border-paper-200 object-cover" />`
                : html`<div key=${'p-'+i} className="w-9 h-9 rounded bg-paper-100 border border-paper-200 grid place-items-center text-[8px] font-mono text-ink-500">IMG</div>`)}
              ${items.length > 3 ? html`<span className="text-[10.5px] text-ink-500 ml-1">+${items.length - 3}</span>` : null}
            </div>
            <div className="text-[10.5px] text-ink-500 font-mono">${items.length} product(s)</div>
          </div>`;
        }
        case 'book_appointment':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">offer </span><span className="font-mono text-ink-900">${d.slotCount || 5} slot(s)</span><div className="text-[10.5px] text-ink-500 truncate">${(d.prompt || 'Pick a time…').slice(0,40)}</div></div>`;
        case 'google_meet':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><div className="font-mono text-[10.5px] text-ink-500">${d.durationMinutes || 30} min · lead ${d.leadMinutes || 5} min</div><div className="text-[12px] text-ink-900 truncate">${d.title || 'Meeting'}</div></div>`;
        case 'wa_form':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><div className="font-mono text-[10.5px] text-ink-500">form #${d.formId || '?'}</div><div className="text-[12px] text-ink-900 truncate">${d.bodyText || 'Tap to open form'}</div></div>`;
        case 'google_sheets':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]">
            <div className="font-mono text-[10.5px] text-ink-500">${(d.mode||'write').toUpperCase()} · ${d.sheetId ? (String(d.sheetId).slice(0,10)+'…') : '(pick sheet)'}</div>
            <div className="text-[12px] text-ink-900 truncate">${d.mode === 'read' ? `lookup ${d.matchColumn || '?'} = ${d.matchValue || '?'}` : `append ${ (d.columns||[]).length } col(s)`}</div>
          </div>`;
        case 'google_docs':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]">
            <div className="font-mono text-[10.5px] text-ink-500">${d.templateId ? ('template '+String(d.templateId).slice(0,10)+'…') : '(pick template)'}</div>
            <div className="text-[12px] text-ink-900 truncate">${d.newTitle || 'Document'}</div>
          </div>`;
        case 'google_form':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]">
            <div className="font-mono text-[10.5px] text-ink-500">${d.formId ? ('form '+String(d.formId).slice(0,10)+'…') : '(pick form)'}</div>
            <div className="text-[12px] text-ink-900 truncate">${d.bodyText || 'Send form link'}</div>
          </div>`;
        case 'deal':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]">
            <div className="font-mono text-[10.5px] text-ink-500">${(d.action === 'move' ? 'MOVE' : 'CREATE')} · ${d.stageId ? ('stage '+d.stageId) : '(pick stage)'}</div>
            <div className="text-[12px] text-ink-900 truncate">${d.dealName || 'New deal'}</div>
          </div>`;
        case 'cf_say':
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble wa whitespace-pre-wrap break-words">${(d.text || 'Say something…').slice(0,120)}</div></div>`;
        case 'cf_listen':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">listen → </span><span className="font-mono text-ink-900">{{${d.save || 'caller_said'}}}</span></div>`;
        case 'cf_ai':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><div className="font-mono text-[10px] text-ink-500">${d.model || 'AI'}${d.assistant ? ' · KB' : ''}</div><div className="text-[12px] text-ink-900 truncate">${(d.prompt || 'AI answers…').slice(0,46)}</div></div>`;
        case 'cf_assistant': {
          const ca = AI_CALL_ASSISTANTS_CACHE.find(a => String(a.id) === String(d.assistant));
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><div className="font-mono text-[10px] text-ink-500">voice agent</div><div className="text-[12px] text-ink-900 truncate">${ca ? ca.name : (d.assistant ? 'Assistant #'+d.assistant : '— pick an assistant —')}</div></div>`;
        }
        case 'cf_menu':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">route by ${d.mode || 'intent'} · </span><span className="font-mono text-ink-900">${(Array.isArray(d.options)?d.options.length:0)} branch(es)</span></div>`;
        case 'cf_search':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">search → </span><span className="font-mono text-ink-900">{{${d.save || 'search'}}}</span></div>`;
        case 'cf_transfer':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">transfer → </span><span className="font-mono text-ink-900">${d.number || '(set number)'}</span></div>`;
        case 'cf_hangup':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px] text-ink-500 italic">${(d.goodbye || 'End the call').slice(0,40)}</div>`;
        case 'ig_send_dm':
          return html`<div className="px-3 pt-2.5 pb-2"><div className="bubble wa whitespace-pre-wrap break-words">${(d.text || 'Send a DM…').slice(0,120)}</div></div>`;
        case 'ig_quick':
          return html`<div className="px-3 pt-2 pb-1.5"><div className="bubble wa whitespace-pre-wrap break-words mb-1">${(d.text||'Pick one…').slice(0,80)}</div><div className="text-[10.5px] text-ink-500 font-mono">${(Array.isArray(d.options)?d.options.length:0)} quick reply(s)</div></div>`;
        case 'ig_buttons':
          return html`<div className="px-3 pt-2 pb-1.5"><div className="bubble wa whitespace-pre-wrap break-words mb-1">${(d.text||'Buttons…').slice(0,80)}</div><div className="text-[10.5px] text-ink-500 font-mono">${(Array.isArray(d.buttons)?d.buttons.length:0)} button(s)</div></div>`;
        case 'ig_reply_comment':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><span className="text-ink-500">public reply · </span><span className="text-ink-900">${(d.message||'…').slice(0,40)}</span></div>`;
        case 'ig_ai':
          return html`<div className="px-3 pt-2 pb-1.5 text-[11.5px]"><div className="font-mono text-[10px] text-ink-500">${d.model || 'AI'}${d.assistant ? ' · KB' : ''}</div><div className="text-[12px] text-ink-900 truncate">${(d.prompt || 'AI answers…').slice(0,46)}</div></div>`;
        case 'ig_products':
          return html`<div className="px-3 pt-2 pb-1.5"><div className="text-[11.5px] text-ink-900 font-medium">Product carousel</div><div className="text-[10.5px] text-ink-500 font-mono">${d.selection === 'all' ? ('first ' + (d.limit || 5) + ' synced') : ((Array.isArray(d.productIds) ? d.productIds.length : 0) + ' product(s)')}</div></div>`;
        case 'ig_ask':
          return html`<div className="px-3 pt-2 pb-1.5"><div className="bubble wa whitespace-pre-wrap break-words mb-1">${(d.question || 'Ask something…').slice(0,80)}</div><div className="text-[10.5px] text-ink-500 font-mono">answer → {{${d.save || 'answer'}}}</div></div>`;
        case 'ig_lead':
          return html`<div className="px-3 pt-2 pb-1.5"><div className="text-[11.5px] text-ink-900 font-medium">Capture lead</div><div className="text-[10.5px] text-ink-500 font-mono">${d.createDeal !== false ? '+ pipeline deal' : 'lead only'}</div></div>`;
        case 'end':
          return null;
      }
      return null;
    }

    const isMultiPort = (node) => {
      const t = typeof node === 'string' ? node : node?.type;
      if (['buttons','list','condition','poll','google_form'].includes(t)) return true;
      // A template node is multi-port when its template has quick-reply buttons.
      if (t === 'template' && typeof node === 'object' && templateQrButtons(node).length) return true;
      // Ask question becomes multi-port once the operator defines
      // expected answers — each one becomes its own branch port.
      if (t === 'ask' && Array.isArray(node?.data?.options) && node.data.options.length > 0) return true;
      return false;
    };

    const NodeCard = memo(function NodeCard({ node, selected, invalid, onMouseDown, onClick, onPortMouseDown, onPortHover, hoveredPort, dragging }) {
      const t = NTYPES[node.type];
      const rows = nodeRows(node);
      const isTrigger = node.type === 'trigger';
      const multi = isMultiPort(node);
      return html`
        <div
          data-node-id=${node.id}
          className=${'node-card absolute select-none ' + (selected ? 'selected ' : '') + (dragging ? 'dragging ' : '') + (invalid ? 'node-invalid ' : '')}
          style=${{ left: node.x, top: node.y, width: NODE_W, ...(invalid ? { boxShadow: '0 0 0 2px #E5484D, 0 8px 22px rgba(229,72,77,0.28)' } : {}) }}
          onMouseDown=${(e) => onMouseDown(e, node.id)}
          onClick=${(e) => { e.stopPropagation(); onClick(node.id); }}
        >
          ${!isTrigger ? html`
            <div
              data-port=${'in:'+node.id}
              className=${'port in ' + (hoveredPort === node.id+':in' ? 'active' : '')}
              style=${{ top: '50%' }}
              onMouseEnter=${() => onPortHover(node.id+':in')}
              onMouseLeave=${() => onPortHover(null)}
              onMouseDown=${(e) => e.stopPropagation()}
              title="input"
            ></div>` : null}

          ${node.isStart ? html`
            <div className="absolute -top-2.5 left-3 px-2 py-0.5 rounded-full text-[9.5px] font-mono font-semibold inline-flex items-center gap-1 z-10" style=${{ background:themeColor('wa-deep'), color:'#fff' }}>
              <svg viewBox="0 0 16 16" className="w-2.5 h-2.5" fill="currentColor"><path d="M8 1.5l1.9 4 4.1.5-3 2.9.8 4-3.8-2-3.8 2 .8-4-3-2.9 4.1-.5z"/></svg>
              START
            </div>` : null}
          <div className="flex items-center gap-2.5 px-3 pt-3 pb-2.5 border-b border-paper-100">
            <span className="chip-icon" style=${{ background: t.bg, color: t.fg }}>
              <${Icon} d=${t.icon} className="w-4 h-4" />
            </span>
            <div className="min-w-0 flex-1">
              <div className="font-semibold text-[13px] leading-tight truncate">${t.label}</div>
              <div className="font-mono text-[9.5px] text-ink-500 truncate">${node.id}</div>
            </div>
            <span className="text-[9.5px] font-mono text-ink-500 px-1.5 py-0.5 rounded bg-paper-50 border border-paper-200 uppercase tracking-wider">${t.group}</span>
          </div>

          ${node.type === 'end' ? html`<div className="px-3 py-4 text-[11.5px] text-ink-500 italic text-center">flow ends here</div>` : null}

          ${renderPreview(node)}

          ${multi ? html`
            <div className="px-2 pt-1 pb-2 border-t border-paper-100">
              ${rows.map((r) => html`
                <div key=${r.id} className="node-row relative flex items-center gap-2 px-2 py-2">
                  <span className="chip-icon-sm" style=${{ background: r.kind === 'yes' ? themeColor('wa-mint') : r.kind === 'no' ? '#FBE9E7' : themeColor('paper-50'), color: r.kind === 'yes' ? themeColor('wa-deep') : r.kind === 'no' ? '#A1431F' : '#3A5A55' }}>
                    <${Icon} d=${r.kind === 'yes' ? 'M3 8l3 3 7-7' : r.kind === 'no' ? 'M4 4l8 8M12 4l-8 8' : 'M3 8h10M9 4l4 4-4 4'} className="w-3 h-3" />
                  </span>
                  <div className=${'min-w-0 flex-1 text-[12px] truncate ' + (r.kind === 'yes' ? 'text-wa-deep font-semibold' : r.kind === 'no' ? 'text-accent-coral font-semibold' : 'text-ink-700')}>${r.label}</div>
                  <div
                    data-port=${'out:'+node.id+':'+r.id}
                    onMouseDown=${(e) => { e.stopPropagation(); onPortMouseDown(e, node.id, r.id); }}
                    onMouseEnter=${() => onPortHover(node.id+':'+r.id)}
                    onMouseLeave=${() => onPortHover(null)}
                    className=${'port out ' + (r.kind === 'yes' ? 'is-yes ' : r.kind === 'no' ? 'is-no ' : '') + (hoveredPort === node.id+':'+r.id ? 'active' : '')}
                    title=${'out: ' + r.label}
                  ></div>
                </div>
              `)}
            </div>
          ` : null}

          ${(!multi && node.type !== 'end') ? html`
            <div
              data-port=${'out:'+node.id+':out'}
              onMouseDown=${(e) => { e.stopPropagation(); onPortMouseDown(e, node.id, 'out'); }}
              onMouseEnter=${() => onPortHover(node.id+':out')}
              onMouseLeave=${() => onPortHover(null)}
              className=${'port out ' + (hoveredPort === node.id+':out' ? 'active' : '')}
              style=${{ top: '50%' }}
              title="output"
            ></div>
          ` : null}
        </div>
      `;
    });

    function Inspector({ node, open, tplVer, onClose, onChange, onDelete, onAddOption, onRemoveOption, onCreateAgent, onPickProducts, onRefresh }) {
      if (!open) return null;
      if (!node) {
        return html`
          <aside className="ins-panel w-[360px] bg-paper-0 border-l border-paper-200 flex flex-col shrink-0 z-20">
            <div className="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-2">
              <div className="flex items-center gap-2.5 min-w-0">
                <span className="chip-icon bg-paper-50">
                  <${Icon} d="M8 1.5a4.5 4.5 0 0 0-2.5 8.3V12h5V9.8A4.5 4.5 0 0 0 8 1.5z" className="w-4 h-4 text-ink-500" />
                </span>
                <div>
                  <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Inspector</div>
                  <div className="font-serif text-[16px] leading-tight">Select a node</div>
                </div>
              </div>
              <button onClick=${onClose} className="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center" title="Hide panel"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto p-5 text-[12.5px] text-ink-500 text-center">
              <svg viewBox="0 0 16 16" className="w-12 h-12 mx-auto text-paper-200" fill="none" stroke="currentColor" strokeWidth="1.3"><circle cx="3.5" cy="8" r="1.8"/><circle cx="12.5" cy="3.5" r="1.8"/><circle cx="12.5" cy="12.5" r="1.8"/><path d="M5 7l6-3M5 9l6 3"/></svg>
              <div className="mt-3 font-serif text-[16px] text-ink-700">Nothing selected</div>
              <div className="text-[12px] text-ink-500 mt-1.5 leading-snug">Click a node on the canvas to edit its properties,<br/>or drag a card from the left to add a new node.</div>
            </div>
          </aside>
        `;
      }
      const t = NTYPES[node.type];
      const d = node.data || {};
      // Helpers return raw html templates instead of being function
      // components. If they were components, htm/preact would see a
      // FRESH function identity on every re-render of Inspector,
      // unmount the form fields, and remount them — killing focus
      // on every keystroke. Inlining the templates lets preact
      // reconcile the same DOM nodes across renders.
      const Field = (label, children, hint) => html`
        <div className="mb-4">
          <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">${label}</label>
          ${children}
          ${hint ? html`<div className="text-[10.5px] text-ink-500 mt-1.5 leading-snug" dangerouslySetInnerHTML=${{__html:hint}}></div>` : null}
        </div>`;
      const Txt = (k, ph='') => html`<input type="text" value=${d[k] ?? ''} onInput=${e => onChange(k, e.target.value)} placeholder=${ph} data-attr-input="true" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />`;
      const Num = (k, ph='') => html`<input type="number" value=${d[k] ?? ''} onInput=${e => onChange(k, parseFloat(e.target.value))} placeholder=${ph} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />`;
      const Ta = (k, ph='', rows=4) => html`<textarea rows=${rows} value=${d[k] ?? ''} onInput=${e => onChange(k, e.target.value)} placeholder=${ph} data-attr-input="true" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] resize-y focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 min-h-[110px] leading-relaxed"></textarea>`;
      // Optional `after` runs once the key is written — used by the Trigger
      // channel picker to clear the sender, since a WhatsApp device id is
      // meaningless on Instagram and vice-versa. updateNodeData uses a
      // functional setNodes, so back-to-back calls compose safely.
      const Sel = (k, opts, after) => html`<select value=${d[k] ?? ''} onChange=${e => { onChange(k, e.target.value); after?.(e.target.value); }} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">${opts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}</select>`;

      const fmtTime = () => {
        const dt = new Date();
        let h = dt.getHours(); const m = dt.getMinutes().toString().padStart(2,'0');
        const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
        return `${h}:${m} ${ap}`;
      };
      const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
      const renderWaText = (s) => escapeHtml(s)
        .replace(/\*([^*\n]+)\*/g, '<b>$1</b>')
        .replace(/_([^_\n]+)_/g, '<i>$1</i>')
        .replace(/~([^~\n]+)~/g, '<s>$1</s>')
        .replace(/```([\s\S]+?)```/g, '<code>$1</code>')
        .replace(/\n/g, '<br>');
      const waBubble = (innerHtml, extraTop) => html`
        <div className="mb-4 rounded-xl p-3" style=${{ background:'#0E2A26' }}>
          <div className="text-[10px] font-mono text-wa-mint/70 mb-2 tracking-[0.16em] uppercase">Preview · WhatsApp</div>
          <div className="rounded-md p-2" style=${{ background:'rgba(13,42,38,0.55)', backgroundImage:'radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px)', backgroundSize:'6px 6px' }}>
            ${extraTop || null}
            <div className="relative max-w-[88%] ml-auto rounded-lg px-2.5 py-1.5 shadow-sm" style=${{ background:'#D9FDD3', color:'#111B21' }}>
              <div className="text-[12.5px] leading-relaxed whitespace-pre-wrap break-words" dangerouslySetInnerHTML=${{ __html: innerHtml }}></div>
              <div className="text-right mt-0.5 text-[9.5px]" style=${{ color:'rgba(0,0,0,0.45)' }}>${fmtTime()} ✓✓</div>
            </div>
          </div>
        </div>`;
      let preview = null;
      if (node.type === 'message') {
        const txt = (d.text || '').trim() ? renderWaText(d.text) : '<i style="opacity:0.55">Your message will appear here</i>';
        preview = waBubble(txt);
      } else if (node.type === 'template') {
        // Pull real template data from the workspace's approved templates.
        // We render the same building blocks the /templates/create live
        // preview shows: attachment block · header · body · footer · buttons.
        const t = findTemplate(d.tpl);
        let inner = '';
        let buttonsHtml = '';
        if (!t && !d.tpl) {
          inner = '<i style="opacity:0.55">Pick an approved template to preview</i>';
        } else if (!t) {
          inner = `<i style="opacity:0.55">Template <code>${escapeHtml(d.tpl)}</code> not found in workspace</i>`;
        } else {
          // Template-name label
          inner += `<div class="text-[9.5px] font-mono uppercase tracking-[0.14em]" style="color:#075E54;opacity:0.7;margin-bottom:2px">${escapeHtml(t.template_name)}</div>`;
          // Optional attachment (image/video/document) → grey block
          // matching template-create's #pp-attach placeholder.
          if (t.attachment_type) {
            const kind = String(t.attachment_type).toLowerCase();
            if ((kind === 'image' || kind === 'photo') && t.attachment_file) {
              inner += `<img src="${escapeHtml(t.attachment_file)}" alt="" style="display:block;max-width:100%;max-height:160px;object-fit:cover;border-radius:5px;margin-bottom:4px"/>`;
            } else {
              inner += `<div style="background:#DFF1ED;border-radius:5px;height:60px;display:flex;align-items:center;justify-content:center;color:#075E54;font-size:10.5px;font-family:monospace;margin-bottom:4px">${escapeHtml(kind)} attachment</div>`;
            }
          }
          // Header (bold) — may also be a media header for some templates;
          // we treat it as text when it's a string.
          if (t.header && typeof t.header === 'string' && t.header.trim()) {
            inner += `<div style="font-weight:600;font-size:12px;margin-bottom:3px">${renderWaText(t.header)}</div>`;
          }
          // Body
          if (t.template_body) inner += renderWaText(t.template_body);
          // Footer
          if (t.footer && t.footer.trim()) {
            inner += `<div style="margin-top:6px;font-size:10.5px;opacity:0.7">${escapeHtml(t.footer)}</div>`;
          }
          // Buttons — stacked under the bubble, white tiles like real WhatsApp.
          const btns = Array.isArray(t.buttons) ? t.buttons : [];
          if (btns.length) {
            buttonsHtml = '<div style="margin:6px -10px -6px;border-radius:0 0 6px 6px;overflow:hidden">'
              + btns.map(b => {
                  const label = b.text || b.title || b.label || '';
                  const kind  = (b.type || b.button_type || '').toLowerCase();
                  const icon  = kind.includes('url') || kind.includes('cta') ? '↗' : (kind.includes('call') || kind.includes('phone') ? '☏' : '');
                  return `<div style="background:#fff;color:#075E54;border-top:1px solid rgba(0,0,0,0.08);text-align:center;padding:6px 0;font-size:12px;font-weight:600">${escapeHtml(label)}${icon ? ' ' + icon : ''}</div>`;
                }).join('')
              + '</div>';
          }
          // Carousel — shown as a horizontal stack of mini cards.
          if (t.carousel_data) {
            let cards = [];
            try { cards = typeof t.carousel_data === 'string' ? JSON.parse(t.carousel_data) : t.carousel_data; } catch (e) { cards = []; }
            if (Array.isArray(cards) && cards.length) {
              buttonsHtml += '<div style="display:flex;gap:6px;overflow-x:auto;margin-top:6px;padding-bottom:2px">'
                + cards.slice(0, 5).map((c) => `<div style="flex:0 0 130px;background:#fff;border-radius:6px;padding:6px;box-shadow:0 1px 1px rgba(0,0,0,0.06);font-size:10.5px;color:#111B21"><div style="font-weight:600;margin-bottom:2px;font-size:11px">${escapeHtml(c.title || c.header || '')}</div><div style="opacity:0.75;line-height:1.3">${escapeHtml((c.body || c.text || '').slice(0, 60))}</div></div>`).join('')
                + '</div>';
            }
          }
        }
        preview = waBubble(inner + buttonsHtml);
      } else if (node.type === 'media') {
        const cap = d.caption ? `<div style="margin-top:4px">${renderWaText(d.caption)}</div>` : '';
        let mediaTop = null;
        if ((d.kind === 'image' || !d.kind) && d.url) {
          mediaTop = html`<img src=${d.url} alt="" className="block max-w-[88%] ml-auto rounded-lg mb-1" style=${{ maxHeight:'160px', objectFit:'cover' }} />`;
        } else if (d.kind === 'video' && d.url) {
          mediaTop = html`<video src=${d.url} controls className="block max-w-[88%] ml-auto rounded-lg mb-1" style=${{ maxHeight:'160px' }}></video>`;
        } else if (d.kind === 'document') {
          mediaTop = html`
            <div className="max-w-[88%] ml-auto rounded-lg px-2.5 py-2 mb-1 flex items-center gap-2" style=${{ background:'#D9FDD3', color:'#111B21' }}>
              <span className="w-7 h-7 rounded-md grid place-items-center text-[9px] font-mono" style=${{ background:themeColor('wa-deep'), color:'#fff' }}>PDF</span>
              <div className="text-[12px] truncate flex-1">${d.filename || 'document.pdf'}</div>
            </div>`;
        } else if (d.kind === 'audio') {
          mediaTop = html`
            <div className="max-w-[88%] ml-auto rounded-lg px-2.5 py-2 mb-1 flex items-center gap-2" style=${{ background:'#D9FDD3', color:'#111B21' }}>
              <span className="w-7 h-7 rounded-full grid place-items-center" style=${{ background:themeColor('wa-deep'), color:'#fff' }}>▶</span>
              <div className="text-[11.5px] flex-1">Voice / audio</div>
            </div>`;
        } else {
          mediaTop = html`<div className="max-w-[88%] ml-auto rounded-lg px-2.5 py-2 mb-1 grid place-items-center" style=${{ background:'rgba(255,255,255,0.06)', color:'rgba(255,255,255,0.55)', minHeight:'80px', fontSize:'11px' }}>(${d.kind || 'image'} preview will appear here)</div>`;
        }
        preview = html`
          <div className="mb-4 rounded-xl p-3" style=${{ background:'#0E2A26' }}>
            <div className="text-[10px] font-mono text-wa-mint/70 mb-2 tracking-[0.16em] uppercase">Preview · WhatsApp</div>
            <div className="rounded-md p-2" style=${{ background:'rgba(13,42,38,0.55)', backgroundImage:'radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px)', backgroundSize:'6px 6px' }}>
              ${mediaTop}
              ${cap ? html`<div className="relative max-w-[88%] ml-auto rounded-lg px-2.5 py-1.5 shadow-sm" style=${{ background:'#D9FDD3', color:'#111B21' }}>
                <div className="text-[12.5px] leading-relaxed whitespace-pre-wrap break-words" dangerouslySetInnerHTML=${{ __html: renderWaText(d.caption) }}></div>
                <div className="text-right mt-0.5 text-[9.5px]" style=${{ color:'rgba(0,0,0,0.45)' }}>${fmtTime()} ✓✓</div>
              </div>` : null}
            </div>
          </div>`;
      } else if (node.type === 'buttons') {
        // Real-WhatsApp layout: the prompt bubble is a self-contained
        // rounded rectangle (closed at the bottom), then 1–3 button
        // tiles hang below as their own rounded rectangles with a tiny
        // gap. Each tile has a small reply-arrow icon next to the
        // label — matches what a customer actually sees.
        const txtHtml = (d.prompt || '').trim() ? renderWaText(d.prompt) : '<i style="opacity:0.55">Button prompt</i>';
        const opts = (d.options || []).slice(0, 3);
        const replyArrow = '<svg viewBox="0 0 16 16" style="width:11px;height:11px;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l4-4v3h2a4 4 0 1 1 0 8h-3"/></svg>';
        const tilesHtml = opts.map(o => `<div style="background:#fff;color:#075E54;border-radius:8px;padding:8px 12px;font-size:12.5px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 1px 1px rgba(0,0,0,0.06)">${replyArrow}<span>${escapeHtml(o)}</span></div>`).join('');
        const tilesBlock = tilesHtml
          ? `<div style="display:flex;flex-direction:column;gap:3px;margin-top:5px;max-width:88%;margin-left:auto">${tilesHtml}</div>`
          : '';
        preview = html`
          <div className="mb-4 rounded-xl p-3" style=${{ background:'#0E2A26' }}>
            <div className="text-[10px] font-mono text-wa-mint/70 mb-2 tracking-[0.16em] uppercase">Preview · WhatsApp · ${opts.length} button(s)</div>
            <div className="rounded-md p-2" style=${{ background:'rgba(13,42,38,0.55)', backgroundImage:'radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px)', backgroundSize:'6px 6px' }}>
              <div className="relative max-w-[88%] ml-auto rounded-lg px-2.5 py-1.5 shadow-sm" style=${{ background:'#D9FDD3', color:'#111B21' }}>
                <div className="text-[12.5px] leading-relaxed whitespace-pre-wrap break-words" dangerouslySetInnerHTML=${{ __html: txtHtml }}></div>
                <div className="text-right mt-0.5 text-[9.5px]" style=${{ color:'rgba(0,0,0,0.45)' }}>${fmtTime()} ✓✓</div>
              </div>
              ${tilesBlock ? html`<div dangerouslySetInnerHTML=${{ __html: tilesBlock }}></div>` : null}
            </div>
          </div>`;
      } else if (node.type === 'list') {
        // Real WhatsApp list message: a normal bubble with the prompt
        // + a SINGLE "opener" button below the bubble. The customer
        // only sees the items AFTER tapping the button — at which
        // point a bottom sheet slides up. We render both: the chat
        // bubble + opener AND a second framed block showing what the
        // customer would see once they tap.
        const txtHtml   = (d.prompt || '').trim() ? renderWaText(d.prompt) : '<i style="opacity:0.55">List prompt</i>';
        const btnLabel  = (d.button || '').trim() || 'View options';
        const opts      = (d.options || []).slice(0, 10).map(listOpt);
        const listIcon  = '<svg viewBox="0 0 16 16" style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 4h11M2.5 8h11M2.5 12h11"/></svg>';
        const openerBtn = `<div style="background:#fff;color:#075E54;border-radius:8px;padding:8px 12px;font-size:12.5px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 1px 1px rgba(0,0,0,0.06);margin-top:5px;max-width:88%;margin-left:auto">${listIcon}<span>${escapeHtml(btnLabel)}</span></div>`;
        // Group by section header exactly the way the runtime does, so the
        // preview matches what Meta actually renders in the bottom sheet.
        const previewGroups = [];
        opts.forEach((o) => {
          const sec = (o.section || '').trim();
          const g = previewGroups.find((x) => x.title === sec);
          if (g) g.rows.push(o); else previewGroups.push({ title: sec, rows: [o] });
        });
        const rowHtml = (o) => `<div style="padding:10px 12px;font-size:12.5px;color:#111B21;border-top:1px solid rgba(0,0,0,0.06);display:flex;align-items:flex-start;justify-content:space-between;gap:8px"><span><span style="display:block">${escapeHtml(o.title.slice(0, LIST_TITLE_MAX))}</span>${o.description ? `<span style="display:block;font-size:11px;color:#667781;margin-top:1px">${escapeHtml(o.description.slice(0, LIST_DESC_MAX))}</span>` : ''}</span><span style="width:14px;height:14px;border:1.5px solid #075E54;border-radius:50%;display:inline-block;flex-shrink:0;margin-top:2px"></span></div>`;
        const itemsHtml = opts.length
          ? previewGroups.map((g) => (g.title ? `<div style="padding:7px 12px 3px;font-size:11px;font-weight:700;color:#075E54;background:#F7F8FA;text-transform:uppercase;letter-spacing:0.04em">${escapeHtml(g.title.slice(0, LIST_SECTION_MAX))}</div>` : '') + g.rows.map(rowHtml).join('')).join('')
          : '<div style="padding:14px;color:rgba(255,255,255,0.55);font-size:11px;font-style:italic;text-align:center">Add items in the Options field below</div>';
        const sheetBlock = `
          <div style="margin-top:10px;border-top:1px dashed rgba(255,255,255,0.12);padding-top:8px">
            <div style="text-align:center;font-family:ui-monospace,monospace;font-size:9.5px;color:rgba(255,255,255,0.55);letter-spacing:0.14em;text-transform:uppercase;margin-bottom:6px">After tap · bottom sheet</div>
            <div style="background:#fff;border-radius:12px 12px 0 0;overflow:hidden;max-width:96%;margin-left:auto;margin-right:auto">
              <div style="text-align:center;padding:6px 0 2px"><span style="display:inline-block;width:36px;height:3px;background:#E0E0E0;border-radius:2px"></span></div>
              <div style="padding:6px 14px 8px;border-bottom:1px solid rgba(0,0,0,0.06);font-weight:600;font-size:12px;color:#111B21">${escapeHtml(btnLabel)}</div>
              ${itemsHtml}
            </div>
          </div>`;
        preview = html`
          <div className="mb-4 rounded-xl p-3" style=${{ background:'#0E2A26' }}>
            <div className="text-[10px] font-mono text-wa-mint/70 mb-2 tracking-[0.16em] uppercase">Preview · WhatsApp · ${opts.length} item(s)</div>
            <div className="rounded-md p-2" style=${{ background:'rgba(13,42,38,0.55)', backgroundImage:'radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px)', backgroundSize:'6px 6px' }}>
              <div className="relative max-w-[88%] ml-auto rounded-lg px-2.5 py-1.5 shadow-sm" style=${{ background:'#D9FDD3', color:'#111B21' }}>
                <div className="text-[12.5px] leading-relaxed whitespace-pre-wrap break-words" dangerouslySetInnerHTML=${{ __html: txtHtml }}></div>
                <div className="text-right mt-0.5 text-[9.5px]" style=${{ color:'rgba(0,0,0,0.45)' }}>${fmtTime()} ✓✓</div>
              </div>
              <div dangerouslySetInnerHTML=${{ __html: openerBtn }}></div>
              <div dangerouslySetInnerHTML=${{ __html: sheetBlock }}></div>
            </div>
          </div>`;
      } else if (node.type === 'cta') {
        // Multi-action preview — bubble with intro line + stack of
        // tile buttons (one per action). Legacy single-kind flows
        // are migrated on-the-fly into the actions[] shape so they
        // render the same way.
        let acts = Array.isArray(d.actions) ? d.actions : null;
        if (!acts) {
          const k = d.kind || 'url';
          const v = k === 'phone' || k === 'call_now' ? (d.phone || '') : k === 'copy' ? (d.code || '') : (d.url || '');
          acts = [{ type: k, label: d.label || 'CTA', value: v }];
        }
        const glyphFor = (t) => t === 'phone' || t === 'call_now' ? '☏' : t === 'copy' ? '⧉' : '↗';
        const hasCopy = acts.some(a => (a.type || 'url') === 'copy');
        // If any action is Copy, surface the code(s) in the bubble
        // body so the customer can long-press to copy. Otherwise the
        // bubble shows a short generic intro line.
        const bubbleParts = [];
        bubbleParts.push('<div style="font-size:12.5px">' + escapeHtml(hasCopy ? 'Tap a button below — or long-press a code to copy.' : 'Tap a button below to continue.') + '</div>');
        acts.forEach(a => {
          if ((a.type || 'url') === 'copy' && a.value) {
            bubbleParts.push(`<div style="margin-top:6px;font-family:ui-monospace,monospace;font-size:16px;font-weight:600;letter-spacing:0.06em;background:rgba(7,94,84,0.08);border-radius:6px;padding:6px 10px;text-align:center;color:#0E2A26">${escapeHtml(a.value)}</div>`);
          }
        });
        const tiles = acts.slice(0, 3).map(a => {
          const t = a.type || 'url';
          const lbl = a.label || (t === 'copy' ? 'Copy code' : t === 'phone' || t === 'call_now' ? 'Call us' : 'Visit');
          return `<div style="background:#fff;color:#075E54;border-radius:8px;padding:8px 12px;font-size:12.5px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 1px 1px rgba(0,0,0,0.06)">${escapeHtml(lbl)} ${glyphFor(t)}</div>`;
        }).join('');
        const tilesBlock = tiles ? `<div style="display:flex;flex-direction:column;gap:3px;margin-top:5px;max-width:88%;margin-left:auto">${tiles}</div>` : '';
        preview = html`
          <div className="mb-4 rounded-xl p-3" style=${{ background:'#0E2A26' }}>
            <div className="text-[10px] font-mono text-wa-mint/70 mb-2 tracking-[0.16em] uppercase">Preview · WhatsApp · ${acts.length} button(s)</div>
            <div className="rounded-md p-2" style=${{ background:'rgba(13,42,38,0.55)', backgroundImage:'radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px)', backgroundSize:'6px 6px' }}>
              <div className="relative max-w-[88%] ml-auto rounded-lg px-2.5 py-1.5 shadow-sm" style=${{ background:'#D9FDD3', color:'#111B21' }}>
                <div className="text-[12.5px] leading-relaxed whitespace-pre-wrap break-words" dangerouslySetInnerHTML=${{ __html: bubbleParts.join('') }}></div>
                <div className="text-right mt-0.5 text-[9.5px]" style=${{ color:'rgba(0,0,0,0.45)' }}>${fmtTime()} ✓✓</div>
              </div>
              ${tilesBlock ? html`<div dangerouslySetInnerHTML=${{ __html: tilesBlock }}></div>` : null}
            </div>
          </div>`;
      } else if (node.type === 'location') {
        // Stylised WA-ish location bubble: title + address + a flat
        // green map placeholder with an SVG pin (no emojis, per house
        // style). Latitude/longitude get a small mono caption below
        // when set so the operator can sanity-check the coordinates.
        const pinSvg = '<svg viewBox="0 0 16 16" style="width:18px;height:18px;color:#E87A5D;filter:drop-shadow(0 1px 1px rgba(0,0,0,0.35))" fill="currentColor"><path d="M8 1.5C5.5 1.5 3.5 3.4 3.5 5.7c0 3 4.5 8.8 4.5 8.8s4.5-5.8 4.5-8.8C12.5 3.4 10.5 1.5 8 1.5zM8 7a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>';
        const coords = (d.lat && d.lng) ? `<div style="margin-top:4px;font-family:ui-monospace,monospace;font-size:10px;opacity:0.6">${escapeHtml(d.lat)}, ${escapeHtml(d.lng)}</div>` : '';
        const inner = `<div style="font-size:11.5px;font-weight:600">${escapeHtml(d.title || 'Pinned location')}</div>`
                    + `<div style="font-size:11px;opacity:0.75">${escapeHtml(d.address || '')}</div>`
                    + `<div style="margin-top:4px;height:64px;border-radius:6px;background:`
                      + `radial-gradient(circle at 30% 30%, rgba(255,255,255,0.35), transparent 40%),`
                      + `linear-gradient(135deg,#A8D5BA,#7AB68B 60%,#5C9A75);`
                      + `position:relative;overflow:hidden">`
                      + `<svg viewBox="0 0 100 60" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;opacity:0.35"><path d="M0 35 Q 25 25 50 35 T 100 30" stroke="#5C9A75" fill="none" stroke-width="0.8"/><path d="M0 50 Q 30 42 60 48 T 100 45" stroke="#5C9A75" fill="none" stroke-width="0.8"/><path d="M20 0 L18 30 L24 30 L22 0Z" fill="#5C9A75" opacity="0.45"/><path d="M70 0 L67 38 L74 38 L71 0Z" fill="#5C9A75" opacity="0.4"/></svg>`
                      + `<span style="position:absolute;left:50%;top:50%;transform:translate(-50%,-100%)">${pinSvg}</span>`
                      + `</div>`
                    + coords;
        preview = waBubble(inner);
      } else if (node.type === 'poll') {
        const q = d.question || 'Poll';
        const opts = (d.options || []).map(o => `<div style="border-top:1px solid rgba(0,0,0,0.08);padding:5px 0;font-size:12px;display:flex;align-items:center;gap:6px"><span style="display:inline-block;width:11px;height:11px;border:1.5px solid #075E54;border-radius:${d.multi ? '2px' : '50%'}"></span>${escapeHtml(o)}</div>`).join('');
        preview = waBubble(`<div style="font-weight:600;font-size:12.5px">${escapeHtml(q)}</div>${opts}`);
      } else if (node.type === 'sequence') {
        // Each reply renders as its own bubble in the dark WhatsApp
        // preview pane, top → bottom, mirroring how the customer
        // will receive them.
        const replies = Array.isArray(d.replies) ? d.replies : [];
        const bubbleHtml = (inner) => `<div style="position:relative;max-width:88%;margin-left:auto;border-radius:8px;padding:6px 10px;background:#D9FDD3;color:#111B21;box-shadow:0 1px 1px rgba(0,0,0,0.06);margin-bottom:5px"><div style="font-size:12.5px;line-height:1.45;white-space:pre-wrap;word-break:break-word">${inner}</div><div style="text-align:right;margin-top:2px;font-size:9.5px;color:rgba(0,0,0,0.45)">${fmtTime()} ✓✓</div></div>`;
        const replyHtml = (r) => {
          const kind = r.type || 'text';
          if (kind === 'text') {
            const t = (r.text || '').trim() ? renderWaText(r.text) : '<i style="opacity:0.55">empty text</i>';
            return bubbleHtml(t);
          }
          let media = '';
          if (kind === 'image' && r.url) {
            media = `<img src="${escapeHtml(r.url)}" alt="" style="display:block;max-width:88%;margin-left:auto;border-radius:8px;margin-bottom:5px;max-height:160px;object-fit:cover"/>`;
          } else if (kind === 'video' && r.url) {
            media = `<div style="max-width:88%;margin-left:auto;border-radius:8px;margin-bottom:5px;background:#0B1F1C;color:#fff;height:120px;display:flex;align-items:center;justify-content:center;font-size:11px;letter-spacing:0.05em">▶ video</div>`;
          } else if (kind === 'audio') {
            media = `<div style="max-width:88%;margin-left:auto;border-radius:8px;margin-bottom:5px;padding:8px 10px;background:#D9FDD3;color:#111B21;display:flex;align-items:center;gap:8px"><span style="width:24px;height:24px;border-radius:50%;background:#075E54;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px">&#9658;</span><span style="font-size:11.5px;flex:1">${escapeHtml(r.filename || 'audio')}</span></div>`;
          } else if (kind === 'document') {
            media = `<div style="max-width:88%;margin-left:auto;border-radius:8px;margin-bottom:5px;padding:8px 10px;background:#D9FDD3;color:#111B21;display:flex;align-items:center;gap:8px"><span style="width:28px;height:28px;border-radius:4px;background:#075E54;color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-family:monospace">DOC</span><span style="font-size:11.5px;flex:1">${escapeHtml(r.filename || 'document')}</span></div>`;
          } else {
            media = `<div style="max-width:88%;margin-left:auto;border-radius:8px;margin-bottom:5px;padding:8px 10px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6);font-size:11px;text-align:center">${escapeHtml(kind)} preview will appear here</div>`;
          }
          if (r.caption) {
            media += bubbleHtml(renderWaText(r.caption));
          }
          return media;
        };
        const stack = replies.length ? replies.map(replyHtml).join('') : '<div style="text-align:center;color:rgba(255,255,255,0.55);font-size:11px;font-style:italic;padding:24px 0">No replies yet</div>';
        preview = html`
          <div className="mb-4 rounded-xl p-3" style=${{ background:'#0E2A26' }}>
            <div className="text-[10px] font-mono text-wa-mint/70 mb-2 tracking-[0.16em] uppercase">Preview · WhatsApp · ${replies.length} reply(s)</div>
            <div className="rounded-md p-2" style=${{ background:'rgba(13,42,38,0.55)', backgroundImage:'radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px)', backgroundSize:'6px 6px' }} dangerouslySetInnerHTML=${{ __html: stack }}>
            </div>
          </div>`;
      }

      let body;
      switch (node.type) {
        case 'trigger': {
          // Single picker endpoint returns tags + groups + devices in one
          // round-trip. Cached on window so the inspector doesn't re-fetch
          // on every keystroke.
          if (!window.__FLOW_PICKER_CACHE__) {
            // Seed the IG accounts from the page immediately so the dropdown is
            // never empty while the fetch is in flight (or if it's cached-empty).
            window.__FLOW_PICKER_CACHE__ = { tags:[], groups:[], devices:[], instagram: IG_ACCOUNTS };
            // cache:'no-store' — the picker is a GET, and a stale browser-cached
            // copy (e.g. fetched before an Instagram account was connected) left
            // the trigger's account dropdown empty even though the server now
            // returns it. Always fetch fresh.
            fetch(APP_BASE + '/flows/api/picker', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
              .then(r => r.ok ? r.json() : null)
              .then(j => { if (j?.ok) {
                // Never clobber the embedded IG accounts with an empty fetch result
                // (a stale cached GET can still slip through some proxies).
                const ig = (Array.isArray(j.instagram) && j.instagram.length) ? j.instagram : IG_ACCOUNTS;
                window.__FLOW_PICKER_CACHE__ = { tags: j.tags || [], groups: j.groups || [], devices: j.devices || [], instagram: ig };
                onRefresh?.();
              } })
              .catch(() => {});
          }
          const tags    = window.__FLOW_PICKER_CACHE__.tags    || [];
          const groups  = window.__FLOW_PICKER_CACHE__.groups  || [];
          const devices = window.__FLOW_PICKER_CACHE__.devices || [];
          const igAccts = window.__FLOW_PICKER_CACHE__.instagram || [];

          // Harden both pickers: only rows with a real id become options, the
          // VALUE is always that id (so tag_added/group_join trigger_value
          // matches the integer id the enrollment engine looks up), and the
          // LABEL never falls through to String(obj) — which rendered a group
          // with no name as a long "[object Object]" blob.
          const tagOpts = [{ v:'', l: tags.length ? '— pick tag —' : 'Loading tags…' }]
            .concat(tags.filter(t => t && t.id != null && String(t.id) !== '')
              .map(t => ({ v: String(t.id), l: String(t.name ?? ('Tag #' + t.id)) })));
          const groupOpts = [{ v:'', l: groups.length ? '— pick group —' : 'Loading groups…' }]
            .concat(groups.filter(g => g && g.id != null && String(g.id) !== '')
              .map(g => ({ v: String(g.id), l: String(g.name ?? g.label ?? ('Group #' + g.id)) })));
          // Which channel this flow runs on. Lives on the TRIGGER node (not the
          // create-flow button) so it can be switched at any time; flowTypeOf()
          // reads it back out to drive the palette and the persisted flow_type.
          const channel = MSG_CHANNELS.includes(d.channel) ? d.channel : INITIAL_FLOW_TYPE;
          const isIg    = channel === 'instagram';

          // Sender options. The VALUE is the composite "engine:id" key — a bare
          // integer is ambiguous because `devices` (Unofficial) and
          // `wa_provider_configs` (WABA/Twilio) are separate auto-increment
          // namespaces whose ids overlap. The server splits it back apart.
          const senderOpts = isIg
            ? [{ v:'', l: igAccts.length ? '— pick Instagram account —' : 'No connected Instagram account' }]
                .concat(igAccts.map(a => ({ v: String(a.key), l: a.label })))
            : [{ v:'', l: devices.length ? '— pick number —' : 'No connected number' }]
                .concat(devices.map(d2 => ({
                  v: String(d2.key ?? d2.id),
                  l: (d2.device_name || d2.phone_number || ('Device #' + d2.id))
                     + (d2.engine_label ? ' · ' + d2.engine_label : ''),
                })));
          // Required — but only when the workspace actually HAS something to
          // pick. A workspace with nothing connected yet (or every number
          // disconnected) would otherwise be unable to save ANY flow, which
          // would strand every existing flow the moment it was reopened.
          // No options → warn, explain, still let them save the draft.
          const senderList    = isIg ? igAccts : devices;
          const senderChosen  = !!String(d.deviceId ?? '').trim();
          const senderMissing = !senderChosen && senderList.length > 0;
          const senderNone    = !senderChosen && senderList.length === 0;

          const stageOpts = [{ v:'', l: STAGES_CACHE.length ? '— pick stage —' : 'Loading stages…' }]
            .concat(STAGES_CACHE.map(s => ({ v: String(s.id), l: s.name })));

          body = html`
            ${/* Hidden entirely when there is nothing to choose between —
                 with the Instagram extension uninstalled WhatsApp is the
                 only channel, and a one-option select is noise that reads
                 like something is missing. */
              (INITIAL_FLOW_TYPE === 'call' || MSG_CHANNELS.length < 2) ? null : Field(
              'Channel',
              // Bound to the RESOLVED channel, not d.channel — flows saved before
              // this picker existed have no `channel` key, and a select whose
              // value matches no option renders the first one while state stays
              // empty (an Instagram flow would have read "WhatsApp").
              html`<select value=${channel}
                onChange=${e => { onChange('channel', e.target.value); onChange('deviceId', ''); }}
                className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                <option value="chat">WhatsApp</option>
                ${/* Rendered from MSG_CHANNELS, never hardcoded. An earlier fix
                     gated MSG_CHANNELS but left these two <option> tags literal,
                     so Instagram kept appearing with the extension uninstalled —
                     a channel you could pick that silently did nothing. */
                  MSG_CHANNELS.includes('instagram')
                    ? html`<option value="instagram">Instagram</option>`
                    : null}
              </select>`,
              'Switching channel re-filters the node palette and clears the sender below — a WhatsApp number is not a valid Instagram account.'
            )}
            ${Field('How does this flow start?', Sel('kind', [
              { v:'keyword',            l:'Keyword match (customer messages us)' },
              { v:'tag_added',          l:'Audience: when a tag is added' },
              { v:'group_join',         l:'Audience: when contact joins a group' },
              { v:'contact_created',    l:'Audience: when a new contact is added' },
              { v:'opt_in',             l:'Audience: when a contact re-subscribes' },
              { v:'order_placed',       l:'Commerce: when an order is placed' },
              { v:'deal_stage_changed', l:'Sales: when a deal enters a stage' },
              { v:'away',               l:'Availability: while Away mode is on' },
              { v:'out_of_hours',       l:'Availability: outside business hours' },
              { v:'manual_enroll',      l:'Manual: operator enrolls contacts' },
            ]))}
            ${d.kind === 'keyword' ? Field('When to run', Sel('keywordMode', [
              { v: 'keywords', l: 'On specific keywords' },
              { v: 'any',      l: 'On any message (default route)' },
            ]), d.keywordMode === 'any'
              ? 'Runs ONLY when no keyword flow matches. Keyword flows always win.'
              : 'Pick "any message" for a catch-all fallback flow.') : null}
            ${d.kind === 'keyword' && d.keywordMode !== 'any'
              ? Field('Keywords', Txt('keywords', 'price, pricing, cost'), 'Comma-separated. Match is fuzzy.')
              : null}
            ${d.kind === 'tag_added' ? Field('Tag that triggers enrollment', Sel('tagId', tagOpts), 'Every contact who gets this tag is auto-enrolled into this flow. Tag is added from /team-inbox or routing rules.') : null}
            ${d.kind === 'group_join' ? Field('Group that triggers enrollment', Sel('groupId', groupOpts), 'Every contact added to this group is auto-enrolled.') : null}
            ${d.kind === 'deal_stage_changed' ? Field('Stage that triggers the flow', Sel('stageId', stageOpts), 'When a deal is moved INTO this pipeline stage, its linked contact is enrolled. Manage stages on the /deals board.') : null}
            ${d.kind === 'order_placed' ? html`
              <div className="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
                Fires for every new order. The order's customer is matched to a saved contact and enrolled.
              </div>
            ` : null}
            ${d.kind === 'contact_created' || d.kind === 'opt_in' ? html`
              <div className="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
                Fires automatically — no extra config needed.
              </div>
            ` : null}
            ${d.kind === 'away' ? html`
              <div className="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
                Fires on any incoming message while <b>Away mode</b> is ON. Toggle Away mode from the <a href="/auto-reply" className="text-wa-deep underline">Auto Reply</a> page. Each contact is enrolled once.
              </div>
            ` : null}
            ${d.kind === 'out_of_hours' ? html`
              <div className="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
                Fires on any incoming message that arrives <b>outside your workspace Business Hours</b>. Set Business Hours in your workspace settings. Each contact is enrolled once.
              </div>
            ` : null}
            ${d.kind === 'manual_enroll' ? html`
              <div className="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
                You'll enroll contacts manually from the <a href="/flows" className="text-wa-deep underline">/flows list</a> via the <b>Enroll</b> button next to this flow.
              </div>
            ` : null}
            ${Field(
              isIg
                ? (d.kind === 'keyword' ? 'Listen on Instagram account' : 'Send from Instagram account')
                : (d.kind === 'keyword' ? 'Listen on number' : 'Send from number'),
              Sel('deviceId', senderOpts),
              isIg
                ? 'Required — which connected Instagram account this flow runs on. Connect one on <a href="/instagram" class="text-wa-deep underline">/instagram</a>.'
                : 'Required — which connected number this flow runs on, across all your engines (Unofficial / WABA / Twilio). Connect one on <a href="/devices" class="text-wa-deep underline">/devices</a>.'
            )}
            ${senderMissing ? html`
              <div className="px-3 py-2 -mt-2 mb-4 rounded-lg bg-red-50 border border-red-200 text-[11.5px] leading-snug text-red-700">
                Pick ${isIg ? 'an Instagram account' : 'a number'} before saving — without one the flow has no channel to run on.
              </div>
            ` : null}
            ${senderNone ? html`
              <div className="px-3 py-2 -mt-2 mb-4 rounded-lg bg-amber-50 border border-amber-200 text-[11.5px] leading-snug text-amber-800">
                ${isIg
                  ? html`No Instagram account is connected to this workspace yet. You can still build and save this flow — connect one on <a href="/instagram" className="underline font-semibold">/instagram</a> and pick it here before publishing.`
                  : html`No connected number in this workspace yet — a disconnected phone can't send, so it isn't listed. You can still build and save this flow — reconnect on <a href="/devices" className="underline font-semibold">/devices</a> and pick it here before publishing.`}
              </div>
            ` : null}
          `;
          break;
        }
        case 'message':
          body = html`${Field('Message text', Ta('text', 'Hi {{name}}! …', 6), "Use <span class='font-mono'>{{var}}</span> or press / to insert a variable.")}`;
          break;
        case 'template': {
          // Build the dropdown from the live workspace templates fetched
          // on builder mount. When the cache is empty we fall back to
          // the saved name so it stays visible until the fetch settles.
          //
          // Picking a template stamps BOTH `tpl` (name, the canonical
          // identifier we send to Node) AND `tplId` (the wa_templates
          // primary key) onto the node data, so the saved flow JSON
          // shows what was wired without having to look up the row.
          const tplOpts = [{ v:'', l: TEMPLATES_CACHE.length ? '— pick template —' : 'Loading templates…' }];
          TEMPLATES_CACHE.forEach(t => {
            const lbl = t.template_name + (t.category ? ' · ' + t.category : '');
            tplOpts.push({ v: t.template_name, l: lbl });
          });
          if (d.tpl && !TEMPLATES_CACHE.some(t => t.template_name === d.tpl)) {
            tplOpts.push({ v: d.tpl, l: d.tpl + ' (not in workspace)' });
          }
          const onPickTpl = (name) => {
            onChange('tpl', name);
            const hit = TEMPLATES_CACHE.find(t => t.template_name === name);
            onChange('tplId', hit ? hit.id : null);
          };
          const tplCustomSelect = html`
            <select value=${d.tpl ?? ''} onChange=${e => onPickTpl(e.target.value)}
              className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
              ${tplOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
            </select>
          `;
          body = html`
            ${Field('Approved template', tplCustomSelect, 'Only approved templates from the workspace appear here. <a href="/templates/create" class="text-wa-deep underline">Create a new one →</a>')}
            ${d.tplId ? html`<div className="mt-1 text-[10.5px] text-ink-500 font-mono">template_id = ${d.tplId}</div>` : null}
          `;
          break;
        }
        case 'media': {
          const onUpload = async (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('type', d.kind || 'image');
            try {
              const res = await fetch(APP_BASE + '/flows/api/upload-media', {
                method: 'POST',
                headers: {
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                  'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
              });
              if (!res.ok) throw new Error('HTTP ' + res.status);
              const data = await res.json();
              const url = data?.data?.url;
              if (url) {
                onChange('url',      url);
                onChange('filename', data.data.filename || file.name);
              }
            } catch (err) {
              window.WaToaster?.error?.('Upload failed: ' + err.message);
            }
          };
          body = html`
            ${Field('Type', Sel('kind', [{v:'image',l:'Image'},{v:'video',l:'Video'},{v:'document',l:'Document'},{v:'audio',l:'Audio'}]))}
            ${Field('Upload', html`
              <label className="block w-full border border-dashed border-wa-deep/50 rounded-lg bg-paper-50 hover:bg-wa-bubble/40 cursor-pointer transition px-3 py-3 text-[12px] text-wa-deep text-center">
                <input type="file" accept=${(d.kind === 'video') ? 'video/*' : (d.kind === 'audio' ? 'audio/*' : (d.kind === 'document' ? '.pdf,.doc,.docx,.xls,.xlsx' : 'image/*'))} onChange=${onUpload} className="hidden" />
                ${d.url ? html`<span>Replace file</span>` : html`<span>Choose ${d.kind || 'image'} (or drag here)</span>`}
              </label>
              ${d.url ? html`
                <div className="mt-2 flex items-start gap-2">
                  ${(d.kind === 'image' || !d.kind) ? html`<img src=${d.url} alt="" className="w-16 h-16 rounded-md object-cover border border-paper-200" />` : html`<span className="w-16 h-16 rounded-md bg-paper-100 grid place-items-center text-[10px] font-mono text-ink-700">${(d.kind || 'file').slice(0,3).toUpperCase()}</span>`}
                  <div className="flex-1 min-w-0">
                    <div className="text-[11px] text-ink-700 truncate">${d.filename || 'uploaded'}</div>
                    <a href=${d.url} target="_blank" rel="noreferrer" className="text-[10.5px] font-mono text-wa-deep hover:underline truncate block">${d.url}</a>
                  </div>
                </div>` : null}
            `, 'Up to 50 MB. We host the file and give the flow a public URL.')}
            ${Field('Or paste a URL', Txt('url', 'https://example.com/image.jpg'))}
            ${Field('Filename (label)', Txt('filename', 'catalogue.pdf'))}
            ${Field('Caption', Ta('caption', 'Optional caption…', 3))}
          `;
          break;
        }
        case 'sequence': {
          // A multi-reply node — the customer receives each reply in order.
          // Power use case: a product card (text + image) followed by a
          // demo video and a PDF spec — all from one node, played in
          // sequence. Each reply is { type, text|url|filename|caption }.
          const replies = Array.isArray(d.replies) ? d.replies : [];
          const setReply = (i, patch) => onChange('replies', replies.map((r, j) => j === i ? { ...r, ...patch } : r));
          const removeReply = (i) => onChange('replies', replies.filter((_, j) => j !== i));
          const moveReply = (i, dir) => {
            const j = i + dir;
            if (j < 0 || j >= replies.length) return;
            const next = replies.slice();
            [next[i], next[j]] = [next[j], next[i]];
            onChange('replies', next);
          };
          const addReply = (type) => {
            const fresh = type === 'text'
              ? { type:'text', text:'' }
              : { type, url:'', filename:'', caption:'' };
            onChange('replies', replies.concat([fresh]));
          };
          const uploadReplyMedia = async (i, kind, file) => {
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('type', kind);
            try {
              const res = await fetch(APP_BASE + '/flows/api/upload-media', {
                method: 'POST',
                headers: {
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                  'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
              });
              if (!res.ok) throw new Error('HTTP ' + res.status);
              const data = await res.json();
              const url = data?.data?.url;
              if (url) setReply(i, { url, filename: data.data.filename || file.name });
            } catch (err) {
              window.WaToaster?.error?.('Upload failed: ' + err.message);
            }
          };
          const typeOpts = [
            { v:'text',     l:'Text'     },
            { v:'image',    l:'Image'    },
            { v:'video',    l:'Video'    },
            { v:'audio',    l:'Audio'    },
            { v:'document', l:'Document' },
          ];
          const acceptFor = (k) =>
            k === 'image'    ? 'image/*' :
            k === 'video'    ? 'video/*' :
            k === 'audio'    ? 'audio/*' :
            k === 'document' ? '.pdf,.doc,.docx,.xls,.xlsx,.txt' : '*/*';

          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Each reply is sent in order to the customer. Combine text, images, video, audio, and documents in one node — perfect for product cards or onboarding kits.
            </div>
            ${replies.map((r, i) => html`
              <div key=${'rep-'+i} className="mb-3 rounded-lg border border-paper-200 bg-paper-0 overflow-hidden">
                <div className="flex items-center gap-2 px-3 py-2 border-b border-paper-200 bg-paper-50/60">
                  <span className="font-mono text-[10px] text-ink-500">${i + 1}.</span>
                  <select value=${r.type || 'text'} onChange=${e => setReply(i, { type: e.target.value })} className="px-2 py-1 border border-paper-200 rounded text-[11.5px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                    ${typeOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
                  </select>
                  <div className="flex-1"></div>
                  <button onClick=${() => moveReply(i, -1)} disabled=${i === 0} className="w-6 h-6 rounded hover:bg-paper-100 grid place-items-center disabled:opacity-30 disabled:cursor-not-allowed" title="Move up"><${Icon} d="M4 10l4-4 4 4" className="w-3 h-3" /></button>
                  <button onClick=${() => moveReply(i, 1)} disabled=${i === replies.length - 1} className="w-6 h-6 rounded hover:bg-paper-100 grid place-items-center disabled:opacity-30 disabled:cursor-not-allowed" title="Move down"><${Icon} d="M4 6l4 4 4-4" className="w-3 h-3" /></button>
                  <button onClick=${() => removeReply(i)} className="w-6 h-6 rounded hover:bg-accent-coral/15 text-accent-coral grid place-items-center" title="Remove"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3 h-3" /></button>
                </div>
                <div className="p-3 space-y-2">
                  ${(r.type || 'text') === 'text' ? html`
                    <textarea rows=${3} value=${r.text || ''} onInput=${e => setReply(i, { text: e.target.value })} placeholder="Message text…" data-attr-input="true" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] resize-y focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                  ` : html`
                    <label className="block w-full border border-dashed border-wa-deep/50 rounded-lg bg-paper-50 hover:bg-wa-bubble/40 cursor-pointer transition px-3 py-2 text-[11.5px] text-wa-deep text-center">
                      <input type="file" accept=${acceptFor(r.type)} onChange=${e => uploadReplyMedia(i, r.type, e.target.files?.[0])} className="hidden" />
                      ${r.url ? html`<span>Replace ${r.type}</span>` : html`<span>Upload ${r.type}</span>`}
                    </label>
                    ${r.url ? html`
                      <div className="flex items-start gap-2 mt-1">
                        ${r.type === 'image' ? html`<img src=${r.url} alt="" className="w-14 h-14 rounded object-cover border border-paper-200" />` : html`<span className="w-14 h-14 rounded bg-paper-100 grid place-items-center text-[9.5px] font-mono text-ink-700 uppercase">${r.type.slice(0,3)}</span>`}
                        <div className="flex-1 min-w-0">
                          <div className="text-[11px] text-ink-700 truncate">${r.filename || 'uploaded'}</div>
                          <a href=${r.url} target="_blank" rel="noreferrer" className="text-[10px] font-mono text-wa-deep hover:underline truncate block">${r.url}</a>
                        </div>
                      </div>` : null}
                    <input type="text" value=${r.url || ''} onInput=${e => setReply(i, { url: e.target.value })} placeholder="Or paste a URL" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[11.5px] font-mono focus:outline-none focus:border-wa-deep" />
                    <input type="text" value=${r.caption || ''} onInput=${e => setReply(i, { caption: e.target.value })} placeholder="Caption (optional)" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  `}
                </div>
              </div>
            `)}
            <div className="flex flex-wrap gap-1.5 mt-1">
              ${typeOpts.map(o => html`
                <button key=${'add-'+o.v} onClick=${() => addReply(o.v)} className="text-[11.5px] px-2.5 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-wa-bubble/40 hover:border-wa-deep/40 text-ink-700 font-medium inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />${o.l}</button>
              `)}
            </div>
            ${replies.length === 0 ? html`<div className="text-[11.5px] text-ink-500 italic mt-2 text-center">No replies yet — click a button above to add one.</div>` : null}
          `;
          break;
        }
        case 'buttons':
          body = html`
            ${Field('Prompt', Ta('prompt', '', 3))}
            ${Field('Options', html`
              ${(d.options || []).map((o, i) => html`
                <div key=${'opt-'+i} className="flex items-center gap-2 mb-1.5">
                  <span className="font-mono text-[10px] text-ink-500 w-5">${i+1}.</span>
                  <input type="text" value=${o} onInput=${e => onChange('options', d.options.map((v,j) => j === i ? e.target.value : v))} className="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  <button onClick=${() => onRemoveOption(i)} className="w-9 h-9 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
                </div>`)}
              <button onClick=${onAddOption} className="mt-1 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add button</button>
            `, 'Max 3 buttons. Each gets its own output port.')}
          `;
          break;
        case 'list': {
          // Items carry title + description + section. The section name is
          // what groups them: items sharing a name render under one heading
          // in WhatsApp's bottom sheet. Storage stays a FLAT array so each
          // item's index remains its output port (p0, p1, …) — reordering
          // sections never re-wires existing edges.
          const listOpts = (d.options || []).map(listOpt);
          const setOpt   = (i, patch) => onChange('options', listOpts.map((v, j) => j === i ? { ...v, ...patch } : v));
          const groups   = [];
          listOpts.forEach((o) => {
            const sec = (o.section || '').trim();
            const g = groups.find((x) => x.title === sec);
            if (g) g.n++; else groups.push({ title: sec, n: 1 });
          });
          const secNames = groups.map((g) => g.title).filter(Boolean);
          const counter  = (len, max) => html`<span className=${'font-mono text-[9.5px] tabular-nums shrink-0 ' + (len > max ? 'text-accent-coral font-bold' : 'text-ink-500')}>${len}/${max}</span>`;
          body = html`
            ${Field('Prompt', Ta('prompt', '', 3))}
            ${Field('Button label', Txt('button', 'View options'), 'Text on the tap-to-open button under the bubble. Max 20 chars on WhatsApp.')}
            ${Field('Items', html`
              <datalist id=${'list-sections-' + node.id}>
                ${secNames.map((s) => html`<option key=${'sec-' + s} value=${s}></option>`)}
              </datalist>
              ${listOpts.map((o, i) => html`
                <div key=${'opt-'+i} className="mb-2 rounded-lg border border-paper-200 bg-paper-50/60 p-2">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-[10px] text-ink-500 w-5">${i+1}.</span>
                    <input type="text" value=${o.title} maxLength=${LIST_TITLE_MAX} onInput=${e => setOpt(i, { title: e.target.value })} placeholder="Item title" className="flex-1 min-w-0 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                    ${counter(o.title.length, LIST_TITLE_MAX)}
                    <button onClick=${() => onRemoveOption(i)} className="w-8 h-8 shrink-0 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
                  </div>
                  <div className="flex items-center gap-2 mt-1.5 pl-7 pr-8">
                    <input type="text" value=${o.description} maxLength=${LIST_DESC_MAX} onInput=${e => setOpt(i, { description: e.target.value })} placeholder="Description (optional)" className="flex-1 min-w-0 px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[11.5px] focus:outline-none focus:border-wa-deep" />
                    ${counter(o.description.length, LIST_DESC_MAX)}
                  </div>
                  <div className="flex items-center gap-2 mt-1.5 pl-7 pr-8">
                    <input type="text" value=${o.section} maxLength=${LIST_SECTION_MAX} list=${'list-sections-' + node.id} onInput=${e => setOpt(i, { section: e.target.value })} placeholder="Section (optional)" className="flex-1 min-w-0 px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[11.5px] focus:outline-none focus:border-wa-deep" />
                    ${counter(o.section.length, LIST_SECTION_MAX)}
                  </div>
                </div>`)}
              <button onClick=${onAddOption} className="mt-1 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add item</button>
              ${groups.length > 1 ? html`<div className="mt-2 text-[11px] text-ink-500">Renders as <b>${groups.length}</b> sections: ${groups.map(g => (g.title || '(no heading)') + ' × ' + g.n).join(' · ')}</div>` : null}
            `, 'Up to 10 items total. Give two items the SAME section name to group them under one heading. Sections and descriptions render on WhatsApp Business API (Cloud); on the Unofficial API the list falls back to plain buttons showing the title only.')}
          `;
          break;
        }
        case 'ask': {
          // Same UX as Quick replies — uses the shared options[] field
          // + onAddOption/onRemoveOption callbacks so the look-and-feel
          // matches exactly. Empty options = free text (single 'out').
          body = html`
            ${Field('Question', Ta('prompt', '', 3))}
            ${Field('Validate as', Sel('validate', [{v:'text',l:'Free text'},{v:'email',l:'Email'},{v:'phone',l:'Phone'},{v:'number',l:'Number'}]))}
            ${Field('Save answer to variable', Txt('var', 'answer'), "The customer's reply is stored here — reuse it later as <span class='font-mono'>{{answer}}</span> (or whatever you name it) in a Send message. IMPORTANT: give each Ask question a UNIQUE name — e.g. <span class='font-mono'>name</span>, <span class='font-mono'>email</span> — otherwise a later question overwrites the earlier answer.")}
            ${Field('Expected answers', html`
              ${(d.options || []).map((o, i) => html`
                <div key=${'ans-'+i} className="flex items-center gap-2 mb-1.5">
                  <span className="font-mono text-[10px] text-ink-500 w-5">${i+1}.</span>
                  <input type="text" value=${o} onInput=${e => onChange('options', d.options.map((v,j) => j === i ? e.target.value : v))} className="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  <button onClick=${() => onRemoveOption(i)} className="w-9 h-9 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
                </div>`)}
              ${(d.options || []).length < 3 ? html`<button onClick=${onAddOption} className="mt-1 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add answer</button>` : html`<div className="mt-1 text-[11px] text-ink-500 italic">Max 3 reached.</div>`}
            `, 'Max 3 answers, same as Quick replies. Each becomes its own output port; anything else goes through the <b>else</b> port. Leave empty for free-text answers.')}
          `;
          break;
        }
        case 'condition': {
          // Compound condition: an array of { variable, operator, value }
          // joined by AND / OR toggles. Same shape the old admin builder
          // used (properties.conditions[] + properties.logicOperators[]).
          const conds = Array.isArray(d.conditions) ? d.conditions : [];
          const ops   = Array.isArray(d.operators)  ? d.operators  : [];
          const setConds = (next) => { onChange('conditions', next); };
          const setOps   = (next) => { onChange('operators', next); };
          const updateRow = (i, key, val) => setConds(conds.map((c, j) => j === i ? { ...c, [key]: val } : c));
          const removeRow = (i) => {
            setConds(conds.filter((_, j) => j !== i));
            // The operator BEFORE the removed row (between i-1 and i) drops too.
            const nextOps = ops.slice();
            if (i > 0 && nextOps.length >= i) nextOps.splice(i - 1, 1);
            else if (nextOps.length) nextOps.splice(0, 1);
            setOps(nextOps);
          };
          const addRow = () => {
            setConds(conds.concat([{ variable:'', operator:'equals', value:'' }]));
            if (conds.length >= 1) setOps(ops.concat(['AND']));
          };
          const setLogic = (i, val) => {
            const next = ops.slice();
            next[i] = val;
            setOps(next);
          };
          const opChoices = [
            { v:'equals',       l:'Equal to' },
            { v:'not_equals',   l:'Not equal to' },
            { v:'contains',     l:'Contains' },
            { v:'not_contains', l:'Does not contain' },
            { v:'gt',           l:'Greater than' },
            { v:'lt',           l:'Less than' },
            { v:'exists',       l:'Is set' },
          ];
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              All the rules below are combined into ONE expression using the <b>AND</b> / <b>OR</b> toggles. The whole expression either evaluates to TRUE (then the <span className="font-mono text-wa-deep">IF · true</span> port fires) or FALSE (then the <span className="font-mono text-accent-coral">ELSE · false</span> port fires). Add rules to make it more specific; chain with <b>AND</b> (all must match) or <b>OR</b> (any can match).
            </div>
            ${conds.map((c, i) => html`
              ${i > 0 ? html`
                <div key=${'op-'+i} className="flex justify-center my-1.5">
                  <div className="inline-flex rounded-full border border-paper-200 bg-paper-50 overflow-hidden text-[11px] font-semibold">
                    <button onClick=${() => setLogic(i - 1, 'AND')} className=${'px-3 py-1 transition ' + ((ops[i-1] || 'AND') === 'AND' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100')}>AND</button>
                    <button onClick=${() => setLogic(i - 1, 'OR')}  className=${'px-3 py-1 transition ' + ((ops[i-1] || 'AND') === 'OR'  ? 'bg-accent-amber text-ink-900' : 'text-ink-600 hover:bg-paper-100')}>OR</button>
                  </div>
                </div>` : null}
              <div key=${'cond-'+i} className="mb-2 rounded-lg border border-paper-200 bg-paper-0 p-2">
                <div className="flex items-center gap-2 mb-1">
                  <span className="font-mono text-[10px] text-ink-500">Rule ${i + 1}</span>
                  <div className="flex-1"></div>
                  ${conds.length > 1 ? html`<button onClick=${() => removeRow(i)} className="w-6 h-6 rounded hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3 h-3" /></button>` : null}
                </div>
                <input type="text" value=${c.variable || ''} onInput=${e => updateRow(i, 'variable', e.target.value)} placeholder="Variable name (e.g. plan)" className="w-full mb-1.5 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                <select value=${c.operator || 'equals'} onChange=${e => updateRow(i, 'operator', e.target.value)} className="w-full mb-1.5 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                  ${opChoices.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
                </select>
                ${(c.operator || 'equals') !== 'exists' ? html`<input type="text" value=${c.value || ''} onInput=${e => updateRow(i, 'value', e.target.value)} placeholder="Compare against…" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />` : null}
              </div>
            `)}
            <button onClick=${addRow} className="mt-1 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add rule</button>
          `;
          break;
        }
        case 'delay':
          body = html`
            ${Field('Amount', Num('amount', '5'))}
            ${Field('Unit', Sel('unit', [{v:'sec',l:'seconds'},{v:'min',l:'minutes'},{v:'hour',l:'hours'},{v:'day',l:'days'}]))}
          `;
          break;
        case 'webhook': {
          const hdrs = Array.isArray(d.headers) ? d.headers : [];
          const setHdr = (i, key, val) => onChange('headers', hdrs.map((h, j) => j === i ? { ...h, [key]: val } : h));
          const addHdr = () => onChange('headers', hdrs.concat([{ key:'', value:'' }]));
          const delHdr = (i) => onChange('headers', hdrs.filter((_, j) => j !== i));
          body = html`
            ${Field('Method', Sel('method', [{v:'POST',l:'POST'},{v:'GET',l:'GET'},{v:'PUT',l:'PUT'},{v:'PATCH',l:'PATCH'},{v:'DELETE',l:'DELETE'}]))}
            ${Field('URL', Txt('url', 'https://api.example.com/stock?field={{field}}'))}
            ${Field('Headers', html`
              ${hdrs.map((h, i) => html`
                <div key=${i} className="flex items-center gap-2 mb-1.5">
                  <input type="text" value=${h.key || ''} onInput=${e => setHdr(i, 'key', e.target.value)} placeholder="Header" className="w-2/5 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />
                  <input type="text" value=${h.value || ''} onInput=${e => setHdr(i, 'value', e.target.value)} placeholder="Value" data-attr-input="true" className="flex-1 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />
                  <button onClick=${() => delHdr(i)} className="text-accent-coral text-[16px] leading-none px-1 shrink-0">×</button>
                </div>
              `)}
              <button onClick=${addHdr} className="text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add header</button>
            `, 'e.g. Authorization → Bearer {{api_token}}. Values can use {{variables}}.')}
            ${Field('Content type', Sel('contentType', [{v:'application/json',l:'JSON'},{v:'application/x-www-form-urlencoded',l:'Form URL-encoded'},{v:'text/plain',l:'Plain text'}]))}
            ${Field('Body', Ta('body', '{ "field": "{{field}}" }', 4), 'Sent for POST/PUT/PATCH. {{variables}} are substituted.')}
            ${Field('Save response to', Txt('save', 'response'), "The API's response is stored in this variable for later nodes (e.g. {{response}}).")}
          `;
          break;
        }
        case 'code': {
          body = html`
            <div className="rounded-lg bg-[#1E2A33] text-[#C7D2CC] border border-paper-200 p-2.5 mb-1 text-[11px] leading-relaxed">
              Runs sandboxed JavaScript. Available: <span className="font-mono text-[#7CF3C4]">previousResponse</span>, <span className="font-mono text-[#7CF3C4]">allResponses</span>, <span className="font-mono text-[#7CF3C4]">functionArgs</span>. <span className="font-mono text-[#7CF3C4]">return</span> an object to save it.
            </div>
            ${Field('JavaScript', html`<textarea value=${d.code ?? ''} onInput=${e => onChange('code', e.target.value)} rows="12" spellcheck="false" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-[#0F1720] text-[#D7E3DC] font-mono text-[12px] leading-relaxed focus:outline-none focus:border-wa-deep" />`, 'No file / network / require access. CPU + memory are capped.')}
            ${Field('Save return value to', Txt('save', 'result'), 'Later nodes read it as {{' + (d.save || 'result') + '}} (or {{' + (d.save || 'result') + '.FIELD}} for object fields).')}
          `;
          break;
        }
        // ───────── Call flow node config panels ─────────
        case 'cf_say': {
          body = html`
            ${Field('Say to caller', Ta('text', 'Hello! Thanks for calling.', 3), 'Spoken aloud (text-to-speech). {{variables}} are substituted.')}
          `;
          break;
        }
        case 'cf_listen': {
          body = html`
            ${Field('Save what they say to', Txt('save', 'caller_said'), 'The caller\'s words are transcribed and stored here, e.g. {{' + (d.save || 'caller_said') + '}}.')}
            ${Field('Stop after silence (seconds)', html`<input type="number" min="1" max="30" value=${d.silenceTimeout ?? 6} onInput=${e => onChange('silenceTimeout', parseInt(e.target.value)||6)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />`, 'How long to wait for them to finish talking before moving on.')}
          `;
          break;
        }
        case 'cf_ai': {
          const modelOpts = AI_MODELS_CACHE.length
            ? [{ v:'', l:'— pick model —' }].concat(AI_MODELS_CACHE.map(m => ({ v:m.value, l:m.label })))
            : [{ v:'', l:'No AI models enabled — ask admin' }];
          if (d.model && !AI_MODELS_CACHE.some(m => m.value === d.model)) modelOpts.push({ v:d.model, l:d.model+' (not enabled)' });
          const aOpts = [{ v:'', l:'— none (prompt only) —' }].concat(AI_ASSISTANTS_CACHE.map(a => ({ v:String(a.id), l:a.name+' · '+(a.sources||0)+' src' })));
          body = html`
            ${Field('Model', Sel('model', modelOpts))}
            ${Field('Instructions (system prompt)', Ta('prompt', 'You are a helpful phone assistant. Keep answers short and natural.', 4))}
            ${Field('Knowledge base (optional)', Sel('assistant', aOpts), 'Attach a trained assistant from AI Training to answer from your content.')}
            ${Field('Save spoken reply to', Txt('save', 'ai_reply'))}
            <label className="flex items-center gap-2 mt-1 text-[12.5px] text-ink-700 cursor-pointer select-none">
              <input type="checkbox" checked=${!!d.endOnGoodbye} onChange=${e => onChange('endOnGoodbye', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
              <span>End the call when the caller says goodbye</span>
            </label>
          `;
          break;
        }
        case 'cf_assistant': {
          const caOpts = [{ v:'', l:'— pick a voice assistant —' }].concat(AI_CALL_ASSISTANTS_CACHE.map(a => ({ v:String(a.id), l:a.name })));
          if (d.assistant && !AI_CALL_ASSISTANTS_CACHE.some(a => String(a.id) === String(d.assistant))) caOpts.push({ v:String(d.assistant), l:'Assistant #'+d.assistant });
          body = html`
            ${Field('Voice assistant', Sel('assistant', caOpts), 'The AI voice agent that answers this turn — its own persona + model reply, spoken back. Put a Listen node before it to make it conversational.')}
            ${Field('Extra instructions (optional)', Ta('prompt', '', 3), 'Appended to the assistant persona for this node only.')}
            ${Field('Save spoken reply to', Txt('save', 'assistant_reply'))}
            <label className="flex items-center gap-2 mt-1 text-[12.5px] text-ink-700 cursor-pointer select-none">
              <input type="checkbox" checked=${!!d.endOnGoodbye} onChange=${e => onChange('endOnGoodbye', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
              <span>End the call when the caller says goodbye</span>
            </label>
          `;
          break;
        }
        case 'cf_menu': {
          const opts = Array.isArray(d.options) ? d.options : [];
          const setOpt = (i,k,val) => onChange('options', opts.map((o,j)=>j===i?{...o,[k]:val}:o));
          const addOpt = () => onChange('options', opts.concat([{ match:'', label:'' }]));
          const delOpt = (i) => onChange('options', opts.filter((_,j)=>j!==i));
          body = html`
            ${Field('Route by', Sel('mode', [{v:'intent',l:'What the caller says (AI intent)'},{v:'keyword',l:'Keyword match'},{v:'digit',l:'Key press (1,2,3…)'}]))}
            ${Field('Branches', html`
              ${opts.map((o,i)=>html`
                <div key=${i} className="flex items-center gap-2 mb-1.5">
                  <input type="text" value=${o.match||''} onInput=${e=>setOpt(i,'match',e.target.value)} placeholder=${d.mode==='digit'?'1':'sales'} className="w-1/3 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />
                  <input type="text" value=${o.label||''} onInput=${e=>setOpt(i,'label',e.target.value)} placeholder="Branch label" className="flex-1 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />
                  <button onClick=${()=>delOpt(i)} className="text-accent-coral text-[16px] leading-none px-1 shrink-0">×</button>
                </div>`)}
              <button onClick=${addOpt} className="text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add branch</button>
            `, 'Each branch becomes an output port. Unmatched calls take the "no match" port.')}
          `;
          break;
        }
        case 'cf_search': {
          body = html`
            ${Field('Search query', Txt('query', '{{caller_said}}'), 'What to look up. Use {{variables}} from earlier nodes.')}
            ${Field('Save results to', Txt('save', 'search'), 'Usually fed into the next AI Respond node via {{' + (d.save || 'search') + '}}.')}
            ${Field('Say while searching', Txt('filler', 'One moment, let me check that…'), 'Spoken immediately so the caller never hears silence while the lookup runs.')}
          `;
          break;
        }
        case 'cf_transfer': {
          body = html`
            ${Field('Transfer to number', Txt('number', '+1555…'), 'The call is bridged to this number (agent / queue).')}
            ${Field('Say before transferring', Txt('message', 'Connecting you to an agent now.'))}
          `;
          break;
        }
        case 'cf_hangup': {
          body = html`
            ${Field('Goodbye message', Txt('goodbye', 'Thanks for calling. Goodbye!'), 'Spoken just before the call ends. Leave blank to hang up silently.')}
          `;
          break;
        }
        case 'ig_send_dm': {
          body = html`
            ${Field('Message', Ta('text', 'Hi! Thanks for your message.', 3), 'The DM text. {{variables}} from earlier nodes are substituted.')}
          `;
          break;
        }
        case 'ig_quick': {
          const opts = Array.isArray(d.options) ? d.options : [];
          const setOpt = (i,k,val) => onChange('options', opts.map((o,j)=>j===i?{...o,[k]:val}:o));
          const addOpt = () => onChange('options', opts.concat([{ title:'', payload:'' }]));
          const delOpt = (i) => onChange('options', opts.filter((_,j)=>j!==i));
          body = html`
            ${Field('Message', Ta('text', 'What can I help you with?', 2))}
            ${Field('Quick replies', html`
              ${opts.map((o,i)=>html`
                <div key=${i} className="flex items-center gap-2 mb-1.5">
                  <input type="text" value=${o.title||''} onInput=${e=>setOpt(i,'title',e.target.value)} placeholder="Button label" className="flex-1 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />
                  <input type="text" value=${o.payload||''} onInput=${e=>setOpt(i,'payload',e.target.value)} placeholder="PAYLOAD" className="w-1/3 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] font-mono focus:outline-none focus:border-wa-deep" />
                  <button onClick=${()=>delOpt(i)} className="text-accent-coral text-[16px] leading-none px-1 shrink-0">×</button>
                </div>`)}
              <button onClick=${addOpt} className="text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add quick reply</button>
            `, 'Each becomes an output port — wire the next node off the one they tap.')}
          `;
          break;
        }
        case 'ig_buttons': {
          const btns = Array.isArray(d.buttons) ? d.buttons : [];
          const setB = (i,k,val) => onChange('buttons', btns.map((b,j)=>j===i?{...b,[k]:val}:b));
          const addB = () => onChange('buttons', btns.concat([{ type:'web_url', title:'', url:'' }]));
          const delB = (i) => onChange('buttons', btns.filter((_,j)=>j!==i));
          body = html`
            ${Field('Message', Ta('text', 'Tap a button below:', 2))}
            ${Field('Buttons (max 3)', html`
              ${btns.map((b,i)=>html`
                <div key=${i} className="mb-2 p-2 border border-paper-200 rounded-lg">
                  <div className="flex items-center gap-2 mb-1">
                    <select value=${b.type||'web_url'} onChange=${e=>setB(i,'type',e.target.value)} className="px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px]"><option value="web_url">Open URL</option><option value="postback">Postback</option></select>
                    <input type="text" value=${b.title||''} onInput=${e=>setB(i,'title',e.target.value)} placeholder="Button label" className="flex-1 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />
                    <button onClick=${()=>delB(i)} className="text-accent-coral text-[16px] leading-none px-1 shrink-0">×</button>
                  </div>
                  ${b.type==='postback'
                    ? html`<input type="text" value=${b.payload||''} onInput=${e=>setB(i,'payload',e.target.value)} placeholder="PAYLOAD" className="w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] font-mono focus:outline-none focus:border-wa-deep" />`
                    : html`<input type="text" value=${b.url||''} onInput=${e=>setB(i,'url',e.target.value)} placeholder="https://…" className="w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" />`}
                </div>`)}
              ${btns.length < 3 ? html`<button onClick=${addB} className="text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add button</button>` : ''}
            `, 'Instagram allows up to 3 buttons per message.')}
          `;
          break;
        }
        case 'ig_reply_comment': {
          body = html`
            ${Field('Public reply', Ta('message', 'Thanks! Just sent you a DM.', 2), 'Posted as a public reply under the comment that triggered the flow.')}
          `;
          break;
        }
        case 'ig_ai': {
          const modelOpts = AI_MODELS_CACHE.length
            ? [{ v:'', l:'— pick model —' }].concat(AI_MODELS_CACHE.map(m => ({ v:m.value, l:m.label })))
            : [{ v:'', l:'No AI models enabled — ask admin' }];
          if (d.model && !AI_MODELS_CACHE.some(m => m.value === d.model)) modelOpts.push({ v:d.model, l:d.model+' (not enabled)' });
          const aOpts = [{ v:'', l:'— none (prompt only) —' }].concat(AI_ASSISTANTS_CACHE.map(a => ({ v:String(a.id), l:a.name+' · '+(a.sources||0)+' src' })));
          body = html`
            ${Field('Model', Sel('model', modelOpts))}
            ${Field('Instructions (system prompt)', Ta('prompt', 'You are our friendly Instagram assistant. Answer briefly.', 4))}
            ${Field('Knowledge base (optional)', Sel('assistant', aOpts), 'Attach a trained assistant from AI Training to answer from your content.')}
            ${Field('Save reply to', Txt('save', 'ai_reply'))}
          `;
          break;
        }
        case 'ig_products': {
          const sel = d.selection || 'pick';
          const seg = (v, l) => html`<button type="button" onClick=${() => onChange('selection', v)} className=${'px-3 py-1.5 rounded-lg text-[12px] font-medium border transition ' + (sel === v ? 'border-wa-deep bg-wa-mint/40 text-wa-deep' : 'border-paper-200 bg-paper-0 text-ink-600 hover:bg-paper-50')}>${l}</button>`;
          const orderOn = d.orderButton !== false;
          const detailsOn = d.detailsButton !== false;
          body = html`
            ${Field('Which products', html`<div className="flex gap-2">${seg('pick', 'Pick specific')}${seg('all', 'First N synced')}</div>`)}
            ${sel === 'all'
              ? Field('How many', Txt('limit', '5'), 'Sends your most recently-synced products (max 10).')
              : Field('Products', html`<${IgProductPicker} value=${d.productIds || []} onChange=${(ids) => onChange('productIds', ids)} />`, 'Tap to add / remove. They send in the order shown.')}
            ${Field('Intro message (optional)', Ta('intro', 'Here are some products you might like:', 2), 'Sent as a DM just before the carousel. Leave blank to send only the cards.')}
            ${Field('Order button', html`
              <label className="flex items-center gap-2 text-[12.5px] mb-1.5"><input type="checkbox" checked=${orderOn} onChange=${e => onChange('orderButton', e.target.checked)} /> Show an “Order” button on each card</label>
              ${orderOn ? Txt('orderLabel', 'Order') : ''}
            `, 'Tapping it sends <span class="font-mono">ORDER_&lt;product&gt;</span> — the ordering flow / keyword rule picks it up.')}
            ${Field('Details button', html`
              <label className="flex items-center gap-2 text-[12.5px] mb-1.5"><input type="checkbox" checked=${detailsOn} onChange=${e => onChange('detailsButton', e.target.checked)} /> Show a “View” link to the product page</label>
              ${detailsOn ? Txt('detailsLabel', 'View') : ''}
            `, 'Only appears on products that have a link in your catalog.')}
          `;
          break;
        }
        case 'ig_ask': {
          body = html`
            ${Field('Question', Ta('question', 'What is your name?', 2), 'Sent as a DM. The next reply the customer sends is captured as the answer.')}
            ${Field('Save answer to variable', Txt('save', 'lead_name'), 'Reuse it later as <span class="font-mono">{{' + (d.save || 'lead_name') + '}}</span> — e.g. in the Capture lead node.')}
          `;
          break;
        }
        case 'ig_lead': {
          body = html`
            <div className="rounded-lg bg-paper-50/60 border border-paper-200 p-2.5 mb-3 text-[11px] text-ink-600">Maps variables collected by earlier <span className="font-mono">Ask a question</span> nodes into a saved lead. Leave a field blank to skip it.</div>
            ${Field('Name from variable', Txt('nameVar', 'lead_name'))}
            ${Field('Email from variable', Txt('emailVar', 'lead_email'))}
            ${Field('Phone from variable', Txt('phoneVar', 'lead_phone'))}
            ${Field('Notes from variable (optional)', Txt('notesVar', ''))}
            ${Field('Sales Pipeline', html`<label className="flex items-center gap-2 text-[12.5px]"><input type="checkbox" checked=${d.createDeal !== false} onChange=${e => onChange('createDeal', e.target.checked)} /> Create a pipeline deal for every lead</label>`, 'The lead is added to your default pipeline so nothing slips through.')}
            ${Field('Thank-you message (optional)', Ta('ack', 'Thanks! Our team will reach out shortly.', 2), 'Sent right after the lead is saved.')}
          `;
          break;
        }
        case 'mysql':
          body = html`
            <div className="rounded-lg bg-paper-50/60 border border-paper-200 p-2.5 mb-1 text-[11px] text-ink-600">Read-only — only <span className="font-mono text-wa-deep">SELECT</span> queries run. Credentials are stored encrypted and only used to fetch data for the reply.</div>
            ${Field('Host', Txt('host', 'db.yoursite.com'))}
            ${Field('Port', Txt('port', '3306'))}
            ${Field('Database', Txt('database', 'my_database'))}
            ${Field('Username', Txt('username', 'db_user'))}
            ${Field('Password', html`<input type="password" value=${d.password ?? ''} onInput=${e => onChange('password', e.target.value)} placeholder="••••••••" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />`)}
            ${Field('SQL query', Ta('sql', 'SELECT qty FROM stock WHERE field = {{field}}', 4), 'SELECT only. Use {{variables}} from earlier nodes (e.g. an AI-extracted field code).')}
            ${Field('Save rows to', Txt('save', 'rows'), 'Returned rows (JSON) are stored here — an AI / message node can then read {{rows}} and answer the customer.')}
          `;
          break;
        case 'ai': {
          // Only models the admin has switched on (admin_ai_keys
          // is_active=true) appear here. The dropdown auto-includes
          // each provider's default model plus any extras the admin
          // configured. If nothing's enabled, show a clear empty state.
          const modelOpts = AI_MODELS_CACHE.length
            ? [{ v: '', l: '— pick model —' }].concat(AI_MODELS_CACHE.map(m => ({ v: m.value, l: m.label })))
            : [{ v: '', l: 'No AI models enabled — ask admin to switch one on' }];
          if (d.model && !AI_MODELS_CACHE.some(m => m.value === d.model)) {
            modelOpts.push({ v: d.model, l: d.model + ' (not enabled)' });
          }
          const aiSave = d.save || 'reply';
          body = html`
            ${Field('Model', Sel('model', modelOpts), AI_MODELS_CACHE.length ? 'Models the admin enabled — plus any provider you added your own key for (Settings → AI keys, on BYOK plans).' : 'No provider available yet. Add your own key in <a href="/settings" class="text-wa-deep underline">Settings → AI keys</a> (BYOK plans), or ask your admin to enable one.')}
            ${Field('System prompt', Ta('prompt', '', 5))}
            ${Field('Save response to', Txt('save', 'reply'), 'Later nodes read it as {{' + aiSave + '}} — or {{' + aiSave + '.FIELD}} when extracting.')}
            ${(() => {
              // Optional AI-Training assistant — attaches its trained
              // knowledge base (URLs/text/Q&A) to this reply.
              const aOpts = [{ v: '', l: '— none (use the prompt above only) —' }].concat(
                AI_ASSISTANTS_CACHE.map(a => ({ v: String(a.id), l: a.name + ' · ' + (a.sources || 0) + ' source' + ((a.sources || 0) === 1 ? '' : 's') })));
              if (d.assistant && !AI_ASSISTANTS_CACHE.some(a => String(a.id) === String(d.assistant))) {
                aOpts.push({ v: String(d.assistant), l: '(assistant #' + d.assistant + ')' });
              }
              const picked = AI_ASSISTANTS_CACHE.find(a => String(a.id) === String(d.assistant));
              const hint = !AI_ASSISTANTS_CACHE.length
                ? 'No trained assistants yet — create one in <a href="/ai-training" class="text-wa-deep underline">AI Training</a>.'
                : (picked && (picked.sources || 0) === 0
                    ? 'Note: this assistant has 0 trained sources — add some in <a href="/ai-training" class="text-wa-deep underline">AI Training</a> or the reply uses the prompt only.'
                    : 'Pulls the chosen assistant\'s trained content into this reply. <a href="/ai-training" class="text-wa-deep underline">Manage knowledge →</a>');
              return Field('Knowledge base (optional)', Sel('assistant', aOpts), hint);
            })()}
            <label className="flex items-center gap-2 mt-1 mb-1 text-[12.5px] text-ink-700 cursor-pointer select-none">
              <input type="checkbox" checked=${!!d.extract} onChange=${e => onChange('extract', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
              <span><strong>Extract structured data (JSON)</strong> — turn the message into fields</span>
            </label>
            ${d.extract ? Field('Fields to extract', Txt('fields', 'FARM, DATE, NAME, FIELD, RATE, VOLUME, EARNINGS'), 'Comma-separated keys. The AI returns ONE JSON object; each key becomes {{' + aiSave + '.KEY}} (and {{' + aiSave + '.__rows}} when several records are detected — e.g. for a Google Sheets node).') : ''}
            <label className="flex items-center gap-2 mt-1 text-[12.5px] text-ink-700 cursor-pointer select-none">
              <input type="checkbox" checked=${!!d.silent} onChange=${e => onChange('silent', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
              <span><strong>Silent</strong> — don't send a reply to the customer (extraction only)</span>
            </label>
            <label className="flex items-center gap-2 mt-2 text-[12.5px] text-ink-700 cursor-pointer select-none">
              <input type="checkbox" checked=${!!d.conversational} onChange=${e => onChange('conversational', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
              <span><strong>Conversation mode</strong> — the AI keeps replying to every message (drives the whole chat) instead of answering once</span>
            </label>
            ${d.conversational ? Field('Exit keyword (optional)', Txt('exit_keyword', 'menu'), 'When the customer types exactly this, the AI hands back and the flow continues to the next node. Leave blank to let the AI run the whole conversation.') : ''}
          `;
          break;
        }
        case 'tag': {
          // Tag picker — real workspace tags from /team-inbox/api/tags.
          // We support two flows:
          //   1. Pick an existing tag from the dropdown (saves tagId).
          //   2. Or type a brand-new tag name in the text input below
          //      (saves the name only; the runtime will create it on
          //      first use). The picker auto-resolves the name → id
          //      so the Node side has both fields.
          const tagsHere = Array.isArray(TAGS_CACHE) ? TAGS_CACHE : [];
          const tagOpts = [{ v: '', l: tagsHere.length ? '— pick existing tag —' : 'No tags yet — type a new name below' }];
          tagsHere.forEach(t => tagOpts.push({ v: String(t.id), l: t.name }));
          if (d.tagId && !tagsHere.some(t => String(t.id) === String(d.tagId))) {
            tagOpts.push({ v: String(d.tagId), l: '(deleted tag #' + d.tagId + ')' });
          }
          // When the user picks from the dropdown, mirror the name into
          // `tag` so the canvas preview + runtime stay in sync.
          const onPickExisting = (id) => {
            const t = tagsHere.find(x => String(x.id) === String(id));
            onChange('tagId', id);
            onChange('tag',   t ? t.name : '');
          };
          body = html`
            ${Field('Action', Sel('action', [{v:'add',l:'Add tag to contact'},{v:'remove',l:'Remove tag from contact'}]))}
            ${Field('Tag', html`
              <select value=${d.tagId || ''} onChange=${e => onPickExisting(e.target.value)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep mb-2">
                ${tagOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
              </select>
              <input type="text" value=${d.tag || ''} onInput=${e => { onChange('tag', e.target.value); onChange('tagId', ''); }} placeholder="…or type a new tag" data-attr-input="true" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
            `, 'Pick from existing tags or type a new name. The contact gets tagged (or untagged) when this node fires.')}
          `;
          break;
        }
        case 'assign': {
          // Live workspace teams + their members. Pick a team to route
          // to the whole queue (uses the team's assignment_strategy —
          // round-robin/load-balanced/etc.) OR pick a specific agent
          // for a hand-off to a named person.
          const teamOpts = TEAMS_CACHE.length
            ? [{ v: '', l: '— pick team —' }].concat(TEAMS_CACHE.map(t => ({ v: String(t.id), l: t.name + (t.members && t.members.length ? ' · ' + t.members.length + ' agent(s)' : '') })))
            : [{ v: '', l: 'No teams yet — create one in /team-inbox' }];
          if (d.team && !TEAMS_CACHE.some(t => String(t.id) === String(d.team)) && d.team !== '') {
            teamOpts.push({ v: d.team, l: d.team + ' (not in workspace)' });
          }
          // Agent picker is scoped to the chosen team's members.
          const selectedTeam = TEAMS_CACHE.find(t => String(t.id) === String(d.team));
          const memberOpts = [{ v: '', l: 'Any (use team strategy)' }];
          if (selectedTeam && Array.isArray(selectedTeam.members)) {
            selectedTeam.members.forEach(m => memberOpts.push({ v: String(m.id), l: m.name }));
          }
          body = html`
            ${Field('Team', Sel('team', teamOpts), TEAMS_CACHE.length ? 'Conversation lands in this team\'s queue. <a href="/team-inbox" class="text-wa-deep underline">Manage teams →</a>' : null)}
            ${selectedTeam ? Field('Assign to specific agent (optional)', Sel('userId', memberOpts), 'Leave as <b>Any</b> to use the team\'s assignment strategy (round-robin / load-balanced / first-online).') : null}
            ${Field('Note for agent', Ta('message', '', 3), 'Internal note shown on the conversation when it lands in the team\'s inbox.')}
          `;
          break;
        }
        case 'subflow': {
          // Live list of the workspace's flows. We exclude the flow
          // being edited so a node can't accidentally call its parent
          // and recurse. Falls back to the saved id label while the
          // fetch is in flight.
          const currentFlowId = window.flowId ?? null;
          const flowOpts = [{ v:'', l: FLOWS_CACHE.length ? '— pick flow —' : 'Loading flows…' }];
          FLOWS_CACHE.forEach(f => {
            if (String(f.id) === String(INITIAL_FLOW_ID)) return; // skip self
            const lbl = (f.flow_name || ('Flow #' + f.id)) + (f.is_published ? ' · live' : ' · draft');
            flowOpts.push({ v: String(f.id), l: lbl });
          });
          if (d.flow && !FLOWS_CACHE.some(f => String(f.id) === String(d.flow))) {
            flowOpts.push({ v: d.flow, l: 'Flow #' + d.flow + ' (not in workspace)' });
          }
          body = html`${Field('Flow to run', Sel('flow', flowOpts), 'Pick another saved flow. When this node fires, that flow runs end-to-end before control returns to the next node here.')}`;
          break;
        }
        case 'cta': {
          // Multi-action CTA — up to 3 buttons combining Visit / Call /
          // Copy in any mix (matches WhatsApp's template button cap of
          // 3). Each action is { type, label, value }.
          //
          // Backwards-compat: a saved flow with the old single-kind
          // shape gets migrated on first edit by reading the legacy
          // fields and synthesizing actions[].
          let actions = Array.isArray(d.actions) ? d.actions : null;
          if (!actions) {
            const k = d.kind || 'url';
            const v = k === 'phone' || k === 'call_now' ? (d.phone || '') : k === 'copy' ? (d.code || '') : (d.url || '');
            actions = [{ type: k, label: d.label || 'CTA', value: v }];
          }
          const typeOpts = [
            { v: 'url',      l: 'Visit website' },
            { v: 'phone',    l: 'Call' },
            { v: 'call_now', l: 'Call now' },
            { v: 'copy',     l: 'Copy code' },
          ];
          const suggestedLabel = (t) => ({ url:'Visit website', phone:'Call us', call_now:'Call now', copy:'Copy code' })[t] || 'Tap';
          const placeholderFor = (t) => t === 'phone' || t === 'call_now' ? '+1 555 123 4567'
                                       : t === 'copy' ? 'SAVE20'
                                       : 'https://example.com';
          const setActions = (next) => onChange('actions', next);
          const patchAction = (i, patch) => setActions(actions.map((a, j) => j === i ? { ...a, ...patch } : a));
          const onTypeChange = (i, newType) => {
            const cur = actions[i] || {};
            const prevSugg = suggestedLabel(cur.type || 'url');
            const nextLabel = (!cur.label || cur.label === prevSugg) ? suggestedLabel(newType) : cur.label;
            patchAction(i, { type: newType, label: nextLabel, value: '' });
          };
          const removeAction = (i) => setActions(actions.filter((_, j) => j !== i));
          const moveAction = (i, dir) => {
            const j = i + dir;
            if (j < 0 || j >= actions.length) return;
            const next = actions.slice();
            [next[i], next[j]] = [next[j], next[i]];
            setActions(next);
          };
          const addAction = () => {
            if (actions.length >= 3) { toast('Max 3 buttons per CTA'); return; }
            setActions(actions.concat([{ type: 'url', label: suggestedLabel('url'), value: '' }]));
          };
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Add up to 3 CTA buttons in one message — mix Visit website, Call, and Copy code in any combination. Same cap WhatsApp template buttons have.
            </div>
            ${actions.map((a, i) => html`
              <div key=${'cta-'+i} className="mb-3 rounded-lg border border-paper-200 bg-paper-0 overflow-hidden">
                <div className="flex items-center gap-2 px-3 py-2 border-b border-paper-200 bg-paper-50/60">
                  <span className="font-mono text-[10px] text-ink-500">${i + 1}.</span>
                  <select value=${a.type || 'url'} onChange=${e => onTypeChange(i, e.target.value)} className="px-2 py-1 border border-paper-200 rounded text-[11.5px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                    ${typeOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
                  </select>
                  <div className="flex-1"></div>
                  <button onClick=${() => moveAction(i, -1)} disabled=${i === 0} className="w-6 h-6 rounded hover:bg-paper-100 grid place-items-center disabled:opacity-30 disabled:cursor-not-allowed" title="Move up"><${Icon} d="M4 10l4-4 4 4" className="w-3 h-3" /></button>
                  <button onClick=${() => moveAction(i, 1)} disabled=${i === actions.length - 1} className="w-6 h-6 rounded hover:bg-paper-100 grid place-items-center disabled:opacity-30 disabled:cursor-not-allowed" title="Move down"><${Icon} d="M4 6l4 4 4-4" className="w-3 h-3" /></button>
                  <button onClick=${() => removeAction(i)} className="w-6 h-6 rounded hover:bg-accent-coral/15 text-accent-coral grid place-items-center" title="Remove"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3 h-3" /></button>
                </div>
                <div className="p-3 space-y-2">
                  <input type="text" value=${a.label || ''} onInput=${e => patchAction(i, { label: e.target.value })} placeholder="Button label" data-attr-input="true" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  <input type="text" value=${a.value || ''} onInput=${e => patchAction(i, { value: e.target.value })} placeholder=${placeholderFor(a.type || 'url')} data-attr-input="true" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                  <div className="text-[10.5px] text-ink-500 leading-snug">
                    ${(a.type === 'phone' || a.type === 'call_now')
                      ? 'Include the country code (e.g. +1…). The customer taps to dial.'
                      : a.type === 'copy'
                      ? 'The customer long-presses the code to copy. Good for coupons, OTPs, referral codes.'
                      : 'The customer taps the button to open this URL in their browser.'}
                  </div>
                </div>
              </div>
            `)}
            ${actions.length < 3
              ? html`<button onClick=${addAction} className="mt-1 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add button</button>`
              : html`<div className="mt-1 text-[11px] text-ink-500 italic">Max 3 buttons reached.</div>`}
          `;
          break;
        }
          break;
        case 'location':
          body = html`
            ${Field('Title', Txt('title', 'Our office'))}
            ${Field('Address', Ta('address', 'Street, city, country', 2))}
            <div className="grid grid-cols-2 gap-2 mb-1">
              ${Field('Latitude', Txt('lat', '12.9716'))}
              ${Field('Longitude', Txt('lng', '77.5946'))}
            </div>
          `;
          break;
        case 'poll':
          body = html`
            ${Field('Question', Ta('question', 'Pick one', 2))}
            ${Field('Options', html`
              ${(d.options || []).map((o, i) => html`
                <div key=${'pollopt-'+i} className="flex items-center gap-2 mb-1.5">
                  <span className="font-mono text-[10px] text-ink-500 w-5">${i+1}.</span>
                  <input type="text" value=${o} onInput=${e => onChange('options', d.options.map((v,j) => j === i ? e.target.value : v))} className="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  <button onClick=${() => onRemoveOption(i)} className="w-9 h-9 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
                </div>`)}
              ${(d.options || []).length < 3
                ? html`<button onClick=${onAddOption} className="mt-1 text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add option</button>`
                : html`<div className="mt-1 text-[11px] text-ink-500 italic">Max 3 reached.</div>`}
            `, 'Max 3 options. Each becomes its own output port.')}
            ${Field('Multiple choice', html`
              <label className="inline-flex items-center gap-2 text-[12px] text-ink-700 cursor-pointer">
                <input type="checkbox" checked=${!!d.multi} onChange=${e => onChange('multi', e.target.checked)} />
                <span>Allow multiple answers</span>
              </label>
            `)}
          `;
          break;
        case 'chatbot': {
          // Workspace's real AI agents from /team-inbox/api/ai-agents.
          // The + button on the right opens an inline "create agent"
          // modal — when it resolves we add the agent to the cache and
          // auto-select it. Provider tag is appended to each option so
          // operators can tell agents apart at a glance.
          const agentOpts = [{ v:'', l: AGENTS_CACHE.length ? '— pick AI agent —' : 'No agents yet — click + to create one' }];
          AGENTS_CACHE.forEach(a => {
            const tag = a.provider && a.model ? ' · ' + a.provider + '/' + a.model : '';
            agentOpts.push({ v: String(a.id), l: (a.name || ('Agent #' + a.id)) + tag });
          });
          if (d.bot && !AGENTS_CACHE.some(a => String(a.id) === String(d.bot))) {
            agentOpts.push({ v: d.bot, l: 'Agent #' + d.bot + ' (not in workspace)' });
          }
          body = html`
            ${Field('AI agent to trigger', html`
              <select value=${d.bot || ''} onChange=${e => onChange('bot', e.target.value)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                ${agentOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
              </select>
              <button
                type="button"
                onClick=${() => {
                  onCreateAgent?.((created) => {
                    if (created && created.id) onChange('bot', String(created.id));
                  });
                }}
                className="mt-2 w-full px-3 py-2 rounded-lg border border-dashed border-wa-deep/50 text-wa-deep bg-paper-50 hover:bg-wa-bubble/40 text-[12px] font-semibold inline-flex items-center justify-center gap-1.5">
                <${Icon} d="M8 3v10M3 8h10" className="w-3.5 h-3.5" />
                Create new AI agent
              </button>
            `, AGENTS_CACHE.length
                ? 'Pick a saved AI agent — its system prompt + model run when this node fires. Use <b>Create new AI agent</b> to add one without leaving the builder.'
                : 'Click <b>Create new AI agent</b> to add your first one. You can also <a href="/team-inbox" class="text-wa-deep underline">manage agents in /team-inbox</a>.')}
          `;
          break;
        }
        case 'whatsapp_shop':
        case 'woocommerce':
        case 'shopify': {
          // Unified commerce-node form — same UX for all three providers.
          // Lazy-loads the workspace's stores for this provider when the
          // inspector opens, and uses the parent FlowApp's product
          // picker modal (passed in via the onPickProducts prop).
          const provider = node.type;
          const stores = COMMERCE_STORES[provider];
          const storeOpts = [{ v: '', l: stores === null ? 'Loading stores…' : (stores.length ? '— pick store —' : (provider === 'whatsapp_shop' ? 'No catalog linked — go to /catalog' : 'No store connected — go to /' + provider)) }];
          (stores || []).forEach(s => storeOpts.push({ v: String(s.id), l: s.name + (s.currency ? ' · ' + s.currency : '') }));
          if (d.storeId && stores && !stores.some(s => String(s.id) === String(d.storeId))) {
            storeOpts.push({ v: d.storeId, l: 'Store #' + d.storeId + ' (not in workspace)' });
          }
          const items = Array.isArray(d.productItems) ? d.productItems : [];
          // Kick off the stores fetch on first render of this inspector.
          // Cache lives on COMMERCE_STORES so flipping between nodes
          // doesn't refetch every time.
          if (stores === null) {
            COMMERCE_STORES[provider] = []; // sentinel — prevents re-trigger
            fetch(APP_BASE + '/flows/api/commerce/stores?provider=' + encodeURIComponent(provider), {
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              credentials: 'same-origin',
            }).then(r => r.ok ? r.json() : null).then(j => {
              if (j && Array.isArray(j.stores)) COMMERCE_STORES[provider] = j.stores;
              onRefresh?.();
            }).catch(() => { COMMERCE_STORES[provider] = []; onRefresh?.(); });
          }
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Pick a store, then pick the products this node will send. On a WABA conversation we send a native catalog message; on Baileys we send each product as a card + a checkout link.
              <div className="mt-1 text-[10.5px] text-ink-500">Ports: <span className="font-mono text-wa-deep">purchased</span> fires when the order webhook lands; <span className="font-mono text-accent-coral">abandoned</span> fires after the wait window with no purchase.</div>
            </div>
            ${Field('Store', html`
              <select value=${d.storeId || ''} onChange=${e => onChange('storeId', e.target.value)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                ${storeOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
              </select>
            `, provider === 'whatsapp_shop'
                ? 'WhatsApp catalog connected via <a href="/catalog" class="text-wa-deep underline">/catalog</a>.'
                : provider === 'woocommerce'
                ? 'WooCommerce stores connected via <a href="/integrations" class="text-wa-deep underline">/integrations</a>.'
                : 'Shopify stores connected via <a href="/shopify" class="text-wa-deep underline">/shopify</a>.')}
            ${Field('Products', html`
              <div className="space-y-1.5">
                ${items.map((p, i) => html`
                  <div key=${'item-'+i} className="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-paper-200 bg-paper-0">
                    ${p.image ? html`<img src=${p.image} alt="" className="w-10 h-10 rounded object-cover border border-paper-200 shrink-0" />`
                              : html`<div className="w-10 h-10 rounded bg-paper-100 border border-paper-200 grid place-items-center text-[9px] font-mono text-ink-500 shrink-0">IMG</div>`}
                    <div className="min-w-0 flex-1">
                      <div className="text-[12px] font-semibold truncate">${p.name || p.retailer_id}</div>
                      <div className="text-[10.5px] text-ink-500 font-mono truncate">${p.currency || ''} ${p.price_minor ? (p.price_minor / 100).toFixed(2) : '—'} · ${p.retailer_id}</div>
                    </div>
                    <button onClick=${() => onChange('productItems', items.filter((_, j) => j !== i))} className="w-7 h-7 rounded hover:bg-accent-coral/15 text-accent-coral grid place-items-center" title="Remove"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3 h-3" /></button>
                  </div>
                `)}
                ${items.length === 0 ? html`<div className="text-[11.5px] text-ink-500 italic">No products yet. Click below to add some.</div>` : null}
                <button
                  type="button"
                  disabled=${!d.storeId}
                  onClick=${() => onPickProducts?.({
                    provider,
                    storeId: d.storeId,
                    already: items.map(p => p.retailer_id),
                    onPicked: (picked) => onChange('productItems', items.concat(picked.filter(p => !items.some(x => x.retailer_id === p.retailer_id))).slice(0, 30)),
                  })}
                  className="mt-1 w-full px-3 py-2 rounded-lg border border-dashed border-wa-deep/50 text-wa-deep bg-paper-50 hover:bg-wa-bubble/40 text-[12px] font-semibold inline-flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                  <${Icon} d="M8 3v10M3 8h10" className="w-3.5 h-3.5" />
                  ${items.length === 0 ? 'Pick products' : 'Pick more products'}
                </button>
              </div>
            `, 'Max 30 products per node. Tap × to remove.')}
            ${Field('Header (optional)',  Txt('headerText',  'Our spring picks'))}
            ${Field('Body',               Ta('bodyText',     'Tap a product to see details', 3))}
            ${Field('Footer (optional)',  Txt('footerText',  'Free shipping over $50'))}
            ${Field('Abandoned-cart wait', html`
              <select value=${d.abandonedWaitMinutes || 5} onChange=${e => onChange('abandonedWaitMinutes', parseInt(e.target.value, 10))} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                <option value="5">5 minutes</option>
                <option value="15">15 minutes</option>
                <option value="60">1 hour</option>
                <option value="240">4 hours</option>
                <option value="1440">1 day</option>
              </select>
            `, 'If no purchase within this window, the <b>abandoned</b> port fires so you can route to a drip / follow-up.')}
          `;
          break;
        }
        case 'book_appointment':
          body = html`
            ${Field('Slots to offer', Num('slotCount', '5'))}
            ${Field('Prompt (sent before the list)', Txt('prompt', 'Pick a time that works for you:'))}
            ${Field('Confirmation message', Txt('confirmation', 'Booked! See you on {{slot}}.'))}
            <div className="mt-2 text-[11px] text-ink-500 font-mono">Books into the Google Calendar you connected at <a href="/appointments/settings" className="text-wa-deep underline">/appointments/settings</a> — nothing to set here.</div>
          `;
          break;
        case 'wa_form': {
          // Lazy-load the workspace's published forms — cached on
          // the window so we don't re-fetch every keystroke.
          if (!window.__WA_FORMS_CACHE__) {
            window.__WA_FORMS_CACHE__ = [];
            fetch('/wa-forms/api/list', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(r => r.ok ? r.json() : null)
              .then(j => { if (j?.forms) { window.__WA_FORMS_CACHE__ = j.forms; onRefresh?.(); } })
              .catch(() => {});
          }
          const forms = window.__WA_FORMS_CACHE__ || [];
          const opts = [{ v: '', l: forms.length ? '— pick form —' : 'Loading forms…' }];
          forms.forEach(f => opts.push({ v: String(f.id), l: f.title + (f.is_published ? '' : ' (draft)') }));
          if (d.formId && !forms.some(f => String(f.id) === String(d.formId))) {
            opts.push({ v: d.formId, l: 'Form #' + d.formId + ' (not loaded)' });
          }
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Sends a published WhatsApp Form to the customer. The flow pauses until they submit, then advances with each field available as <code className="font-mono">{{field_id}}</code>.
              <div className="mt-1 text-[10.5px] text-ink-500">Build + publish forms at <a href="/wa-forms" className="text-wa-deep underline">/wa-forms</a>.</div>
            </div>
            ${Field('Form', Sel('formId', opts), forms.length ? 'Only published forms appear here.' : 'Loading…')}
            ${Field('Body bubble text (above the form button)', Txt('bodyText', 'Please tap below to fill out our quick form.'))}
            ${Field('CTA label (on the button)', Txt('ctaLabel', 'Open form'))}
            ${Field('Save submission under variable', Txt('flowVariable', 'form_submission'), 'Field values land as <code class="font-mono">{{field_id}}</code> regardless — this is just a marker for the audit log.')}
          `;
          break;
        }
        case 'google_meet':
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Creates a Google Calendar event with a Meet link the moment this node fires, then sends the link to the customer.
              <div className="mt-1 text-[10.5px] text-ink-500">Requires Google Calendar at <a href="/appointments/settings" className="text-wa-deep underline">/appointments/settings</a>.</div>
            </div>
            ${Field('Meeting title', Txt('title', 'WhatsApp consultation with {{name}}'), 'Shown in Google Calendar. Use {{name}} or any flow variable.')}
            ${Field('Duration (minutes)', Num('durationMinutes', '30'), 'How long the calendar event lasts.')}
            ${Field('Lead time (minutes)', Num('leadMinutes', '5'), 'Schedule the meeting N minutes from now, so the customer has time to read the message.')}
            ${Field('Message template', Ta('messageTemplate', 'Your meeting link:\\n{{meet_link}}\\n\\nStarts {{meet_start}}', 4),
              'Sent to the customer with the link. {{meet_link}} and {{meet_start}} get substituted.')}
            ${Field('Send Google invite to attendees', html`
              <label className="inline-flex items-center gap-2 text-[12.5px] cursor-pointer">
                <input type="checkbox" checked=${!!d.sendCalendarInvite} onChange=${e => onChange('sendCalendarInvite', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
                <span>${d.sendCalendarInvite ? 'On — Google emails the attendees' : 'Off — link only via WhatsApp'}</span>
              </label>
            `, 'Only useful when the contact has an email saved.')}
          `;
          break;
        case 'google_sheets': {
          // Lazy-load the workspace's Sheets via the picker API.
          if (!window.__G_SHEETS_CACHE__) {
            window.__G_SHEETS_CACHE__ = { files: [], integration: null };
            fetch('/api/google/picker/sheets', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(r => r.ok ? r.json() : null)
              .then(j => { if (j?.ok) { window.__G_SHEETS_CACHE__ = j; onRefresh?.(); } })
              .catch(() => {});
          }
          const cache = window.__G_SHEETS_CACHE__;
          const integ = cache?.integration || {};
          const files = cache?.files || [];
          // `cache.ok` is set only AFTER the picker request returns — lets us
          // tell "still loading" from "loaded but the account has no sheets"
          // (both previously showed "Loading sheets…" forever).
          const loaded = cache?.ok === true;
          const emptyLabel = loaded ? 'No spreadsheets found — paste a URL/ID below' : 'Loading sheets…';
          const opts = [{ v: '', l: files.length ? '— pick a sheet —' : emptyLabel }];
          files.forEach(f => opts.push({ v: f.id, l: f.name }));
          if (d.sheetId && !files.some(f => f.id === d.sheetId)) {
            opts.push({ v: d.sheetId, l: 'Sheet ' + String(d.sheetId).slice(0, 12) + '… (not loaded)' });
          }
          const consentNeeded = integ.connected === false || (Array.isArray(integ.missing) && integ.missing.includes('sheets'));
          const cols = Array.isArray(d.columns) ? d.columns : [];
          const setCols = (next) => onChange('columns', next);
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Append a row to a Google Sheet (write mode) or look up a row by a key column (read mode). Uses the workspace's connected Google account at <a href="/google-account" className="text-wa-deep underline">/google-account</a>.
              ${consentNeeded ? html`<div className="mt-1 text-[10.5px] text-accent-coral">⚠ Sheets scope missing — reconnect Google to enable.</div>` : null}
            </div>
            ${Field('Mode', html`
              <select value=${d.mode || 'write'} onChange=${e => onChange('mode', e.target.value)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                <option value="write">Write — append a row</option>
                <option value="read">Read — look up a row</option>
              </select>
            `)}
            ${Field('Spreadsheet', Sel('sheetId', opts))}
            ${Field('…or paste a Spreadsheet URL / ID', html`
              <input type="text" defaultValue=${d.sheetId || ''}
                placeholder="https://docs.google.com/spreadsheets/d/<ID>/edit"
                onChange=${e => { const v = String(e.target.value || '').trim(); const m = v.match(/\/d\/([a-zA-Z0-9_-]{20,})/); onChange('sheetId', m ? m[1] : v); }}
                className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12px] font-mono focus:outline-none focus:border-wa-deep" />
            `, 'Works even if the picker is empty — paste the sheet link and we extract its ID.')}
            ${Field('Tab name (blank = first tab)', Txt('tabName', 'Sheet1'))}
            ${d.mode === 'read' ? html`
              ${Field('Lookup column (header)', Txt('matchColumn', 'Phone'))}
              ${Field('Lookup value', Txt('matchValue', '{{phone}}'), 'Use a flow variable like {{phone}} or {{email}}.')}
              ${Field('Save row under variable', Txt('saveAs', 'sheet_row'), 'Cells become <code class="font-mono">{{saveAs.Name}}</code>, <code class="font-mono">{{saveAs.Phone}}</code> etc.')}
            ` : html`
              ${Field(d.matchByHeader ? 'Columns (matched by header name)' : 'Columns to append (in sheet order)', html`
                <div className="space-y-2">
                  ${cols.map((c, i) => html`
                    <div key=${'col-'+i} className="flex items-center gap-2">
                      <input value=${c.header || ''} onChange=${e => { const n = cols.slice(); n[i] = { ...n[i], header: e.target.value }; setCols(n); }} placeholder="Header" className="w-1/3 px-2 py-1.5 border border-paper-200 rounded text-[12px]" />
                      <input value=${c.value || ''} onChange=${e => { const n = cols.slice(); n[i] = { ...n[i], value: e.target.value }; setCols(n); }} placeholder="{{name}}" className="flex-1 px-2 py-1.5 border border-paper-200 rounded text-[12px]" />
                      <button onClick=${() => setCols(cols.filter((_, j) => j !== i))} className="w-7 h-7 grid place-items-center rounded hover:bg-accent-coral/15 text-accent-coral" title="Remove">×</button>
                    </div>
                  `)}
                  <button onClick=${() => setCols(cols.concat([{ header: '', value: '' }]))} className="text-[11.5px] text-wa-deep hover:underline">+ Add column</button>
                </div>
              `, d.matchByHeader
                  ? 'Each value is written under the sheet column whose header matches the Header field — any order. Values support {{merge_tags}}.'
                  : 'Header is informational (the row is appended left-to-right by position). Values support {{merge_tags}}.')}
              <label className="flex items-start gap-2 cursor-pointer mt-1 mb-2 px-1">
                <input type="checkbox" checked=${!!d.matchByHeader} onChange=${e => onChange('matchByHeader', e.target.checked)} className="mt-0.5 accent-wa-deep" />
                <span className="text-[12px] text-ink-800">Match columns by header name
                  <span className="block text-[10.5px] text-ink-500">Write each value into the sheet column with that header (any order). Leave off to append left-to-right by position — the default.</span>
                </span>
              </label>
            `}
          `;
          break;
        }
        case 'google_docs': {
          if (!window.__G_DOCS_CACHE__) {
            window.__G_DOCS_CACHE__ = { files: [], integration: null };
            fetch('/api/google/picker/docs', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(r => r.ok ? r.json() : null)
              .then(j => { if (j?.ok) { window.__G_DOCS_CACHE__ = j; onRefresh?.(); } })
              .catch(() => {});
          }
          const cache = window.__G_DOCS_CACHE__;
          const integ = cache?.integration || {};
          const files = cache?.files || [];
          const opts = [{ v: '', l: files.length ? '— pick a template doc —' : 'Loading docs…' }];
          files.forEach(f => opts.push({ v: f.id, l: f.name }));
          if (d.templateId && !files.some(f => f.id === d.templateId)) {
            opts.push({ v: d.templateId, l: 'Doc ' + String(d.templateId).slice(0, 12) + '… (not loaded)' });
          }
          const consentNeeded = integ.connected === false || (Array.isArray(integ.missing) && (integ.missing.includes('docs') || integ.missing.includes('drive')));
          const phs = Array.isArray(d.placeholders) ? d.placeholders : [];
          const setPhs = (next) => onChange('placeholders', next);
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Copy a template doc, fill <code className="font-mono">{{placeholders}}</code>, share anyone-with-link, send the URL to the customer. Place <code className="font-mono">{{key}}</code> in your template where the value should go.
              ${consentNeeded ? html`<div className="mt-1 text-[10.5px] text-accent-coral">⚠ Docs or Drive scope missing — reconnect Google.</div>` : null}
            </div>
            ${Field('Template document', Sel('templateId', opts))}
            ${Field('New copy title', Txt('newTitle', 'Document for {{name}}'))}
            ${Field('Placeholders to fill', html`
              <div className="space-y-2">
                ${phs.map((p, i) => html`
                  <div key=${'ph-'+i} className="flex items-center gap-2">
                    <input value=${p.key || ''} onChange=${e => { const n = phs.slice(); n[i] = { ...n[i], key: e.target.value }; setPhs(n); }} placeholder="customer_name" className="w-1/3 px-2 py-1.5 border border-paper-200 rounded text-[12px] font-mono" />
                    <input value=${p.value || ''} onChange=${e => { const n = phs.slice(); n[i] = { ...n[i], value: e.target.value }; setPhs(n); }} placeholder="{{name}}" className="flex-1 px-2 py-1.5 border border-paper-200 rounded text-[12px]" />
                    <button onClick=${() => setPhs(phs.filter((_, j) => j !== i))} className="w-7 h-7 grid place-items-center rounded hover:bg-accent-coral/15 text-accent-coral" title="Remove">×</button>
                  </div>
                `)}
                <button onClick=${() => setPhs(phs.concat([{ key: '', value: '' }]))} className="text-[11.5px] text-wa-deep hover:underline">+ Add placeholder</button>
              </div>
            `, 'Every flow variable is also auto-injected — so <code class="font-mono">{{name}}</code> works in the template without listing it here.')}
            ${Field('Make link viewable by anyone', html`
              <label className="inline-flex items-center gap-2 text-[12.5px] cursor-pointer">
                <input type="checkbox" checked=${d.shareable !== false} onChange=${e => onChange('shareable', e.target.checked)} className="w-4 h-4 accent-wa-deep" />
                <span>${d.shareable !== false ? 'On — anyone with link can view' : 'Off — must be granted manually'}</span>
              </label>
            `)}
            ${Field('Message template', Ta('messageTemplate', "Here's your document:\\n{{doc_url}}", 4), 'The bot bubble sent to the customer. <code class="font-mono">{{doc_url}}</code> gets the share link.')}
            ${Field('Save URL under variable', Txt('saveAs', 'doc_url'))}
          `;
          break;
        }
        case 'google_form': {
          if (!window.__G_FORMS_CACHE__) {
            window.__G_FORMS_CACHE__ = { files: [], integration: null };
            fetch('/api/google/picker/forms', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
              .then(r => r.ok ? r.json() : null)
              .then(j => { if (j?.ok) { window.__G_FORMS_CACHE__ = j; onRefresh?.(); } })
              .catch(() => {});
          }
          const cache = window.__G_FORMS_CACHE__;
          const integ = cache?.integration || {};
          const files = cache?.files || [];
          const opts = [{ v: '', l: files.length ? '— pick a form —' : 'Loading forms…' }];
          files.forEach(f => opts.push({ v: f.id, l: f.name }));
          if (d.formId && !files.some(f => f.id === d.formId)) {
            opts.push({ v: d.formId, l: 'Form ' + String(d.formId).slice(0, 12) + '… (not loaded)' });
          }
          const consentNeeded = integ.connected === false || (Array.isArray(integ.missing) && integ.missing.includes('forms'));
          const scriptUrl = d.formId ? `/google-account/forms/${encodeURIComponent(d.formId)}/apps-script.gs` : '';
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Sends the form URL via WhatsApp, pauses the flow, resumes via the <b>submitted</b> port when the customer fills it. Answers become flow variables.
              ${consentNeeded ? html`<div className="mt-1 text-[10.5px] text-accent-coral">⚠ Forms scope missing — reconnect Google.</div>` : null}
            </div>
            ${Field('Google Form', Sel('formId', opts))}
            ${Field('Body bubble (sent above the link)', Ta('bodyText', 'Please fill out this short form:', 3))}
            ${Field('Save responses under variable', Txt('saveAs', 'google_form'), 'Individual answers also land as <code class="font-mono">{{saveAs.Question title}}</code>.')}
            ${Field('Expire after (seconds)', Num('expiresInSec', '86400'), 'How long to wait for a submission before timing out. 86400 = 24h.')}
            ${d.formId ? html`
              <div className="mt-3 p-3 rounded-lg border border-paper-200 bg-paper-50">
                <div className="font-semibold text-[12px] mb-1">One-time setup — paste this into your Form's Script editor</div>
                <ol className="list-decimal list-inside text-[11.5px] text-ink-700 space-y-0.5 mb-2">
                  <li>Open your form → <span className="font-mono">⋮ menu</span> → <span className="font-mono">Script editor</span></li>
                  <li><a href=${scriptUrl} download className="text-wa-deep underline">Download the script (.gs)</a> and paste it</li>
                  <li>Add a trigger: <span className="font-mono">onWaDeskFormSubmit</span> · From form · On form submit</li>
                </ol>
                <div className="text-[10.5px] text-ink-500 font-mono">Without this script the form will still record answers in Google Forms, but the flow won't resume.</div>
              </div>
            ` : null}
          `;
          break;
        }
        case 'deal': {
          // Sales Pipeline stages load from /deals/stages (STAGES_CACHE,
          // shared with the trigger node's stage picker).
          const stageOpts = [{ v:'', l: STAGES_CACHE.length ? '— pick stage —' : 'Loading stages…' }]
            .concat(STAGES_CACHE.map(s => ({ v: String(s.id), l: s.name })));
          if (d.stageId && !STAGES_CACHE.some(s => String(s.id) === String(d.stageId))) {
            stageOpts.push({ v: String(d.stageId), l: 'Stage ' + d.stageId + ' (not loaded)' });
          }
          body = html`
            <div className="mb-3 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
              Creates a CRM deal (or moves the contact's open deal) into a pipeline stage — so a chatbot can qualify a lead and push it up the board. The deal id is saved as <code className="font-mono">{{${d.saveAs || 'deal_id'}}}</code>. Moving into a stage fires the <b>deal stage</b> trigger on other flows.
            </div>
            ${Field('Action', Sel('action', [
              { v:'create', l:'Create a new deal' },
              { v:'move',   l:'Move the contact\'s deal to a stage' },
            ]), d.action === 'move' ? 'Re-stages the contact\'s most recent open deal. If they have none, a new deal is created.' : 'Always creates a fresh deal linked to this contact.')}
            ${Field('Pipeline stage', Sel('stageId', stageOpts), 'Required. Manage stages on the <a href="/deals" target="_blank" className="text-wa-deep underline">deals board</a>.')}
            ${Field('Deal name', Txt('dealName', '{{contact_name}} — deal'), 'Supports {{contact_name}}, {{phone}}, and any flow variable.')}
            ${Field('Deal value (optional)', Txt('value', '5000'), 'A number or a {{variable}}. Stored in the pipeline currency.')}
            ${Field('Save deal id under variable', Txt('saveAs', 'deal_id'), 'Use <code class="font-mono">{{deal_id}}</code> in later nodes.')}
          `;
          break;
        }
        case 'end':
          body = html`<div className="text-[12.5px] text-ink-700 leading-relaxed">This node terminates the flow. Anything connected after it won't run.</div>`;
          break;
      }
      return html`
        <aside className="ins-panel w-[360px] bg-paper-0 border-l border-paper-200 flex flex-col shrink-0 z-20">
          <div className="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-2">
            <div className="flex items-center gap-2.5 min-w-0">
              <span className="chip-icon" style=${{ background: t.bg, color: t.fg }}>
                <${Icon} d=${t.icon} className="w-4 h-4" />
              </span>
              <div className="min-w-0">
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">${t.group}</div>
                <div className="font-serif text-[16px] leading-tight truncate">${t.label}</div>
              </div>
            </div>
            <div className="flex items-center gap-1">
              <button onClick=${onDelete} className="w-8 h-8 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center" title="Delete (⌫)"><${Icon} d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" className="w-3.5 h-3.5" /></button>
              <button onClick=${onClose} className="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center" title="Hide panel"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
          </div>
          <div className="flex-1 overflow-y-auto p-4 text-[12.5px] text-ink-500">
            <div className="mb-3 text-[10.5px] font-mono text-ink-500 flex items-center gap-2"><span>id</span><span className="text-ink-700">${node.id}</span></div>
            ${preview}
            ${body}
          </div>
        </aside>
      `;
    }

    /**
     * Save-confirm modal. Click "Save" in the toolbar → open this →
     * confirm/edit the flow name → click Save → actually POST. Keeps
     * the user from accidentally saving with a stale or auto-generated
     * name, and gives a clear "saved" anchor before they jump into
     * Test or Publish.
     */
    function SaveConfirmModal({ open, initialName, busy, onSave, onClose }) {
      const [name, setName] = useState(initialName || '');
      useEffect(() => { if (open) setName(initialName || ''); }, [open, initialName]);
      if (!open) return null;
      const submit = () => {
        const trimmed = (name || '').trim();
        if (!trimmed || busy) return;
        onSave(trimmed);
      };
      return html`
        <div onClick=${e => { if (e.target === e.currentTarget && !busy) onClose(); }} className="fixed inset-0 z-50 flex items-center justify-center p-5" style=${{ background:'rgba(11,31,28,0.46)' }}>
          <div className="bg-paper-0 rounded-2xl w-full max-w-[460px] shadow-soft border border-paper-200">
            <div className="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
              <div>
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Save flow</div>
                <h3 className="font-serif text-[20px] leading-tight tracking-[-0.01em]">Confirm name, then save</h3>
                <p className="mt-0.5 text-[12px] text-ink-500">Saving keeps your nodes + edges as a draft. Publish separately when you're ready to run it.</p>
              </div>
              <button onClick=${onClose} disabled=${busy} className="w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center disabled:opacity-40"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
            <div className="px-5 py-4 space-y-3">
              <label className="block">
                <span className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">Flow name</span>
                <input
                  value=${name}
                  onChange=${e => setName(e.target.value)}
                  onKeyDown=${e => e.key === 'Enter' && submit()}
                  autoFocus
                  placeholder="e.g. Welcome flow"
                  className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
              </label>
            </div>
            <div className="px-5 py-3 border-t border-paper-200 flex items-center justify-end gap-2 bg-paper-50/40">
              <button onClick=${onClose} disabled=${busy} className="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px]">Cancel</button>
              <button onClick=${submit} disabled=${busy || !name.trim()} className="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 disabled:opacity-50">
                ${busy ? html`<svg className="w-3.5 h-3.5 animate-spin" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="8" cy="8" r="6" strokeDasharray="20 8"/></svg>` : html`<${Icon} d="M2 8l5 5 7-9" className="w-3 h-3" />`}
                ${busy ? 'Saving…' : 'Save flow'}
              </button>
            </div>
          </div>
        </div>
      `;
    }

    function AIGenModal({ open, onClose, onGenerate, initialPrompt, flowType }) {
      // Models come from /flows/api/ai-models (admin_ai_keys). Hardcoding
      // a provider list was the old behaviour and would let users pick a
      // model the server isn't configured for. AI_MODELS_CACHE is
      // populated on FlowApp mount.
      const PROVIDER_META = {
        openai:    { label:'OpenAI',    dot:'#10A37F' },
        anthropic: { label:'Anthropic', dot:'#D97757' },
        gemini:    { label:'Google',    dot:'#4285F4' },
        mistral:   { label:'Mistral',   dot:'#FA520F' },
      };
      const [model, setModel] = useState(AI_MODELS_CACHE[0]?.value || '');
      const [prompt, setPrompt] = useState(initialPrompt || '');
      const [loading, setLoading] = useState(false);
      const [error, setError]     = useState(null);
      const examples = [
        'Customer support bot with name, email collection',
        'Order tracking with order number verification',
        'Lead qualification bot with budget questions',
        'Appointment booking with date and time selection',
      ];
      useEffect(() => {
        if (!open) return;
        setError(null);
        setLoading(false);
        if (!model && AI_MODELS_CACHE.length) setModel(AI_MODELS_CACHE[0].value);
      }, [open]);
      if (!open) return null;
      const selectedEntry = AI_MODELS_CACHE.find(m => m.value === model);
      const generate = async () => {
        if (!prompt.trim() || !selectedEntry) return;
        setError(null);
        setLoading(true);
        try {
          const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
          const res = await fetch(APP_BASE + '/flows/api/ai-generate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type':     'application/json',
              'Accept':           'application/json',
              'X-CSRF-TOKEN':     csrf,
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              prompt:    prompt.trim(),
              model:     selectedEntry.value,
              provider:  selectedEntry.provider,
              flow_type: flowType || 'chat',
            }),
          });
          const j = await res.json().catch(() => null);
          if (!res.ok || !j || !j.ok) {
            setError(j?.message || ('Generation failed (' + res.status + ')'));
            setLoading(false);
            return;
          }
          onGenerate(j.flow, selectedEntry.label || selectedEntry.value);
          setLoading(false);
          setPrompt('');
          onClose();
        } catch (e) {
          setError(e?.message || 'Network error');
          setLoading(false);
        }
      };
      // Build the per-provider tiles from what admin enabled. One tile
      // per (provider, model) so the user picks the exact runtime model.
      const models = AI_MODELS_CACHE.map(m => ({
        id:    m.value,
        label: m.label.split(' · ')[1] || m.label,
        sub:   PROVIDER_META[m.provider]?.label || m.provider,
        dot:   PROVIDER_META[m.provider]?.dot   || '#888',
      }));
      return html`
        <div onClick=${e => { if (e.target === e.currentTarget && !loading) onClose(); }} className="fixed inset-0 z-50 flex items-center justify-center p-5" style=${{ background:'rgba(11,31,28,0.46)' }}>
          <div className="bg-paper-0 rounded-2xl w-full max-w-[640px] max-h-[88vh] flex flex-col shadow-soft border border-paper-200">
            <div className="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
              <div>
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">AI Flow Generator</div>
                <h3 className="font-serif text-[22px] leading-tight tracking-[-0.01em]">Generate a flow with AI</h3>
                <p className="mt-0.5 text-[12px] text-ink-500">Pick a model, describe what you want, and AI will build the nodes.</p>
              </div>
              <button onClick=${onClose} disabled=${loading} className="w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center disabled:opacity-40"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              <div>
                <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">Choose model</label>
                ${models.length === 0 ? html`
                  <div className="rounded-lg border border-paper-200 bg-paper-50 px-3 py-3 text-[12px] text-ink-700">
                    No AI providers enabled. Admin needs to add a key in <a href="/admin/api-keys" className="text-wa-deep underline">AI Keys</a> before this can run.
                  </div>
                ` : html`
                  <div className="grid gap-2 ${models.length >= 3 ? 'grid-cols-3' : models.length === 2 ? 'grid-cols-2' : 'grid-cols-1'}">
                    ${models.map(m => html`
                      <button key=${m.id} onClick=${() => setModel(m.id)}
                        className=${'flex flex-col items-start gap-1 p-3 rounded-xl border transition ' + (model === m.id ? 'border-wa-deep bg-wa-mint/30' : 'border-paper-200 bg-paper-0 hover:bg-paper-50')}>
                        <span className="flex items-center gap-1.5">
                          <span className="w-2 h-2 rounded-full" style=${{ background: m.dot }}></span>
                          <span className="text-[12.5px] font-semibold truncate">${m.label}</span>
                        </span>
                        <span className="text-[10.5px] text-ink-500">${m.sub}</span>
                      </button>
                    `)}
                  </div>
                `}
              </div>
              ${error ? html`<div className="rounded-lg border border-accent-coral/30 bg-accent-coral/10 text-accent-coral text-[12px] px-3 py-2">${error}</div>` : null}
              <div>
                <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">Describe your flow</label>
                <textarea rows=${5} value=${prompt} onChange=${e => setPrompt(e.target.value)}
                  placeholder="Example: Create a customer support bot that asks for user name, email, and issue type, then sends a confirmation message…"
                  className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] resize-y focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
              </div>
              <div>
                <div className="text-[11px] font-semibold text-ink-700 mb-1.5">Quick examples</div>
                <div className="space-y-1">
                  ${examples.map((ex, i) => html`
                    <button key=${i} onClick=${() => setPrompt(ex)}
                      className="w-full text-left px-3 py-1.5 rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[11.5px] text-ink-700 inline-flex items-center gap-2">
                      <${Icon} d="M3 8l3 3 7-7" className="w-3 h-3 text-wa-deep" />
                      <span className="truncate">${ex}</span>
                    </button>
                  `)}
                </div>
              </div>
            </div>
            <div className="px-5 py-3 border-t border-paper-200 flex items-center justify-between bg-paper-50/40">
              <div className="text-[10.5px] text-ink-500">
                ${loading ? 'Generating…' : 'AI will replace the current flow.'}
              </div>
              <div className="flex items-center gap-2">
                <button onClick=${onClose} disabled=${loading} className="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px]">Cancel</button>
                <button onClick=${generate} disabled=${loading || !prompt.trim() || !selectedEntry}
                  className="px-3.5 py-1.5 rounded-full text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 disabled:opacity-50"
                  style=${{ background:'linear-gradient(135deg,#7B57C7,#3D7CD3)' }}>
                  ${loading ? html`<svg className="w-3.5 h-3.5 animate-spin" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="8" cy="8" r="6" strokeDasharray="20 8"/></svg>` : html`<${Icon} d="M8 1v3M8 12v3M1 8h3M12 8h3" className="w-3 h-3" />`}
                  Generate
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    /**
     * Product picker modal — opened from any commerce node's inspector
     * "Pick products" button. Live-fetches the store's products via
     * /flows/api/commerce/stores/{id}/products?provider=… with a 60-s
     * cache on the server. Multi-select + search.
     *
     * Props:
     *   open       — bool
     *   ctx        — { provider, storeId, already:[retailer_id], onPicked:(items)=>void }
     *   onClose    — () => void
     */
    function ProductPickerModal({ open, ctx, onClose }) {
      const [products, setProducts] = useState([]);
      const [loading, setLoading]   = useState(true);
      const [error, setError]       = useState(null);
      const [q, setQ]               = useState('');
      const [picked, setPicked]     = useState({}); // retailer_id → product
      useEffect(() => {
        if (!open || !ctx?.storeId) return;
        setLoading(true); setError(null); setProducts([]); setPicked({}); setQ('');
        const url = '/flows/api/commerce/stores/' + encodeURIComponent(ctx.storeId)
                  + '/products?provider=' + encodeURIComponent(ctx.provider || '')
                  + '&limit=50';
        fetch(url, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        })
          .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
          .then(j => {
            if (!j || !Array.isArray(j.products)) throw new Error('bad response');
            setProducts(j.products);
            setLoading(false);
          })
          .catch(e => { setError(e.message || 'fetch failed'); setLoading(false); });
      }, [open, ctx?.storeId, ctx?.provider]);
      if (!open) return null;
      const filtered = q
        ? products.filter(p => (p.name || '').toLowerCase().includes(q.toLowerCase()) || (p.sku || '').toLowerCase().includes(q.toLowerCase()))
        : products;
      const togglePick = (p) => {
        const id = String(p.retailer_id);
        setPicked(curr => {
          const next = { ...curr };
          if (next[id]) delete next[id]; else next[id] = p;
          return next;
        });
      };
      const confirm = () => {
        ctx?.onPicked?.(Object.values(picked));
        onClose?.();
      };
      const pickedCount = Object.keys(picked).length;
      return html`
        <div onClick=${e => { if (e.target === e.currentTarget) onClose(); }} className="fixed inset-0 z-50 flex items-center justify-center p-5" style=${{ background:'rgba(11,31,28,0.46)' }}>
          <div className="bg-paper-0 rounded-2xl w-full max-w-[720px] max-h-[88vh] flex flex-col shadow-soft border border-paper-200">
            <div className="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
              <div>
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Pick products</div>
                <h3 className="font-serif text-[22px] leading-tight tracking-[-0.01em]">${({ shopify:'Shopify products', woocommerce:'WooCommerce products', whatsapp_shop:'WhatsApp catalog products' }[ctx?.provider]) || 'Products'}</h3>
                <p className="mt-0.5 text-[12px] text-ink-500">Tap products to add. Tap again to remove. Already-on-node items are skipped.</p>
              </div>
              <button onClick=${onClose} className="w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
            <div className="px-5 py-3 border-b border-paper-200">
              <div className="relative">
                <svg viewBox="0 0 16 16" className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" strokeWidth="1.5"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
                <input value=${q} onInput=${e => setQ(e.target.value)} type="search" placeholder="Search by name or SKU…" className="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
              </div>
            </div>
            <div className="flex-1 overflow-y-auto p-3">
              ${loading ? html`<div className="text-center py-12 text-[12.5px] text-ink-500 italic">Loading products…</div>` : null}
              ${error   ? html`<div className="m-3 rounded-lg border border-accent-coral/30 bg-accent-coral/10 text-accent-coral text-[12px] px-3 py-2">Couldn't load products: ${error}</div>` : null}
              ${!loading && !error && filtered.length === 0 ? html`<div className="text-center py-12 text-[12.5px] text-ink-500 italic">No products found. ${q ? 'Try a different search.' : 'Connect or sync the store first.'}</div>` : null}
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                ${filtered.map(p => {
                  const id = String(p.retailer_id);
                  const isAlreadyOnNode = (ctx?.already || []).includes(id);
                  const isPicked = !!picked[id];
                  return html`
                    <button key=${id} onClick=${() => !isAlreadyOnNode && togglePick(p)}
                      disabled=${isAlreadyOnNode || !p.in_stock}
                      className=${'text-left rounded-lg border p-2 transition '
                        + (isPicked ? 'border-wa-deep bg-wa-mint/30 ' : 'border-paper-200 bg-paper-0 hover:bg-paper-50 ')
                        + (isAlreadyOnNode || !p.in_stock ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer')}>
                      ${p.image
                        ? html`<img src=${p.image} alt="" className="w-full aspect-square rounded object-cover border border-paper-200 mb-2" />`
                        : html`<div className="w-full aspect-square rounded bg-paper-100 border border-paper-200 grid place-items-center text-[11px] font-mono text-ink-500 mb-2">IMG</div>`}
                      <div className="text-[12px] font-semibold leading-tight line-clamp-2">${p.name}</div>
                      <div className="text-[10.5px] text-ink-500 font-mono mt-1">${p.currency || ''} ${p.price_minor ? (p.price_minor / 100).toFixed(2) : '—'}</div>
                      <div className="flex items-center gap-1 mt-1">
                        ${!p.in_stock ? html`<span className="text-[9.5px] font-mono text-accent-coral">out of stock</span>` : null}
                        ${isAlreadyOnNode ? html`<span className="text-[9.5px] font-mono text-ink-500">on node</span>` : null}
                        ${isPicked ? html`<span className="text-[9.5px] font-mono text-wa-deep inline-flex items-center gap-0.5"><${Icon} d="M3 8l3 3 7-7" className="w-2.5 h-2.5" /> picked</span>` : null}
                      </div>
                    </button>
                  `;
                })}
              </div>
            </div>
            <div className="px-5 py-3 border-t border-paper-200 flex items-center justify-between bg-paper-50/40">
              <div className="text-[12px] text-ink-500">${pickedCount} selected</div>
              <div className="flex items-center gap-2">
                <button onClick=${onClose} className="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px]">Cancel</button>
                <button onClick=${confirm} disabled=${pickedCount === 0} className="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold disabled:opacity-40 disabled:cursor-not-allowed">Add ${pickedCount} to node</button>
              </div>
            </div>
          </div>
        </div>`;
    }

    /**
     * Inline product picker for the Instagram "Send products" node. Lazy-fetches
     * the workspace's synced catalog from /instagram/commerce/products.json and
     * renders a searchable, multi-select grid. `value` is an array of
     * instagram_products.id; toggling emits the new array (selection order
     * preserved). Defined at module scope so its identity is stable across
     * Inspector re-renders (a fresh identity would remount + reset its state).
     */
    function IgProductPicker({ value, onChange }) {
      const [products, setProducts] = useState([]);
      const [loading, setLoading]   = useState(true);
      const [error, setError]       = useState(null);
      const [q, setQ]               = useState('');
      useEffect(() => {
        let alive = true;
        setLoading(true); setError(null);
        fetch('/instagram/commerce/products.json', {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        })
          .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
          .then(j => { if (!alive) return; setProducts(Array.isArray(j.products) ? j.products : []); setLoading(false); })
          .catch(e => { if (!alive) return; setError(e.message || 'fetch failed'); setLoading(false); });
        return () => { alive = false; };
      }, []);
      const selected = (value || []).map(Number);
      const has = (id) => selected.includes(Number(id));
      const toggle = (id) => {
        const n = Number(id);
        onChange(has(n) ? selected.filter(x => x !== n) : selected.concat([n]));
      };
      const filtered = q ? products.filter(p => (p.name || '').toLowerCase().includes(q.toLowerCase())) : products;
      return html`
        <div>
          <input value=${q} onInput=${e => setQ(e.target.value)} type="search" placeholder="Search products…" className="w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12px] mb-2 focus:outline-none focus:border-wa-deep" />
          ${loading ? html`<div className="text-[12px] text-ink-500 italic py-3 text-center">Loading synced products…</div>` : null}
          ${error ? html`<div className="text-[12px] text-accent-coral py-2">Couldn't load: ${error}</div>` : null}
          ${!loading && !error && products.length === 0 ? html`<div className="text-[12px] text-ink-500 italic py-3 text-center leading-snug">No products synced yet.<br/>Sync your catalog on the <a href="/instagram/commerce" target="_blank" className="text-wa-deep underline">Commerce page</a> first.</div>` : null}
          <div className="max-h-[220px] overflow-y-auto grid grid-cols-2 gap-1.5">
            ${filtered.map(p => {
              const on = has(p.id);
              return html`<button key=${p.id} type="button" onClick=${() => toggle(p.id)} className=${'flex items-center gap-2 text-left rounded-lg border p-1.5 transition ' + (on ? 'border-wa-deep bg-wa-mint/30' : 'border-paper-200 bg-paper-0 hover:bg-paper-50')}>
                ${p.image_url
                  ? html`<img src=${p.image_url} alt="" className="w-8 h-8 rounded object-cover border border-paper-200 shrink-0" />`
                  : html`<div className="w-8 h-8 rounded bg-paper-100 border border-paper-200 shrink-0"></div>`}
                <div className="min-w-0 flex-1">
                  <div className="text-[11.5px] font-medium truncate">${p.name}</div>
                  <div className="text-[10px] text-ink-500 font-mono truncate">${p.currency || ''} ${p.price != null ? Number(p.price).toFixed(2) : '—'}</div>
                </div>
                ${on ? html`<${Icon} d="M3 8l3 3 7-7" className="w-3 h-3 text-wa-deep shrink-0" />` : null}
              </button>`;
            })}
          </div>
          <div className="text-[10.5px] text-ink-500 mt-1.5">${selected.length} selected · sent in this order</div>
        </div>`;
    }

    function NewAgentModal({ open, onClose, onCreated }) {
      const [name, setName]     = useState('');
      const [modelV, setModelV] = useState('');
      const [tone, setTone]     = useState('professional');
      const [prompt, setPrompt] = useState('You are a helpful support assistant. Reply briefly.');
      const [saving, setSaving] = useState(false);
      const [error, setError]   = useState(null);
      // Reset every time the modal opens so a leftover error / draft
      // doesn't haunt the next open.
      useEffect(() => {
        if (!open) return;
        setName('');
        setModelV(AI_MODELS_CACHE[0]?.value || '');
        setTone('professional');
        setPrompt('You are a helpful support assistant. Reply briefly.');
        setError(null);
        setSaving(false);
      }, [open]);
      if (!open) return null;

      const save = async () => {
        setError(null);
        if (!name.trim()) { setError('Name is required.'); return; }
        const modelEntry = AI_MODELS_CACHE.find(m => m.value === modelV);
        if (!modelEntry) { setError('Pick a model. If the list is empty, ask admin to enable one in /admin/api-keys.'); return; }
        setSaving(true);
        try {
          const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
          const res = await fetch('/team-inbox/api/ai-agents', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              name: name.trim(),
              provider: modelEntry.provider,
              model: modelEntry.value,
              tone,
              system_prompt: prompt,
              auto_respond: true,
              is_active: true,
            }),
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok) {
            throw new Error(data?.message || data?.errors?.name?.[0] || ('HTTP ' + res.status));
          }
          const agent = data?.agent || data?.data || data;
          // Push into the module cache so the dropdown sees it next render.
          if (agent && agent.id) AGENTS_CACHE.push(agent);
          onCreated?.(agent);
          onClose?.();
        } catch (e) {
          setError(e.message || 'Save failed');
        } finally {
          setSaving(false);
        }
      };

      const modelOpts = AI_MODELS_CACHE.length
        ? AI_MODELS_CACHE.map(m => ({ v: m.value, l: m.label }))
        : [{ v: '', l: 'No models enabled — admin must switch one on' }];

      return html`
        <div onClick=${e => { if (e.target === e.currentTarget && !saving) onClose(); }} className="fixed inset-0 z-50 flex items-center justify-center p-5" style=${{ background:'rgba(11,31,28,0.46)' }}>
          <div className="bg-paper-0 rounded-2xl w-full max-w-[560px] max-h-[88vh] flex flex-col shadow-soft border border-paper-200">
            <div className="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
              <div>
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">New AI agent</div>
                <h3 className="font-serif text-[22px] leading-tight tracking-[-0.01em]">Create AI agent</h3>
                <p className="mt-0.5 text-[12px] text-ink-500">Pick a model and write the system prompt. The agent will be saved to the workspace and selected on this node.</p>
              </div>
              <button onClick=${onClose} disabled=${saving} className="w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center disabled:opacity-40"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              <div>
                <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">Name</label>
                <input type="text" value=${name} onInput=${e => setName(e.target.value)} placeholder="e.g. Support Triage" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
              </div>
              <div>
                <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">Model</label>
                <select value=${modelV} onChange=${e => setModelV(e.target.value)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                  ${modelOpts.map(o => html`<option key=${o.v} value=${o.v}>${o.l}</option>`)}
                </select>
                <div className="text-[10.5px] text-ink-500 mt-1 leading-snug">Only admin-enabled providers appear. <a href="/admin/api-keys" className="text-wa-deep underline">Manage keys →</a></div>
              </div>
              <div>
                <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">Tone</label>
                <select value=${tone} onChange=${e => setTone(e.target.value)} className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                  <option value="friendly">Friendly</option>
                  <option value="professional">Professional</option>
                  <option value="concise">Concise</option>
                  <option value="empathetic">Empathetic</option>
                </select>
              </div>
              <div>
                <label className="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">System prompt</label>
                <textarea rows=${6} value=${prompt} onInput=${e => setPrompt(e.target.value)} placeholder="You are a friendly support assistant…" className="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] resize-y focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
              </div>
              ${error ? html`<div className="rounded-lg border border-accent-coral/30 bg-accent-coral/10 text-accent-coral text-[12px] px-3 py-2">${error}</div>` : null}
            </div>
            <div className="px-5 py-3 border-t border-paper-200 flex items-center justify-end gap-2 bg-paper-50/40">
              <button onClick=${onClose} disabled=${saving} className="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px]">Cancel</button>
              <button onClick=${save} disabled=${saving || !AI_MODELS_CACHE.length} className="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold disabled:opacity-50">
                ${saving ? 'Saving…' : 'Create agent'}
              </button>
            </div>
          </div>
        </div>`;
    }

    function VarsModal({ open, vars, onChange, onAdd, onRemove, onClose }) {
      if (!open) return null;
      return html`
        <div onClick=${e => { if (e.target === e.currentTarget) onClose(); }} className="fixed inset-0 z-50 flex items-center justify-center p-5" style=${{ background:'rgba(11,31,28,0.46)' }}>
          <div className="bg-paper-0 rounded-2xl w-full max-w-[680px] max-h-[88vh] flex flex-col shadow-soft border border-paper-200">
            <div className="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
              <div>
                <div className="font-serif text-[16px] italic text-wa-deep leading-tight">flow vars</div>
                <h3 className="font-serif text-[22px] leading-tight tracking-[-0.01em]">Variables</h3>
                <p className="mt-0.5 text-[12px] text-ink-500">Reference these as <span className="font-mono">{{var_name}}</span> in any message or condition.</p>
              </div>
              <button onClick=${onClose} className="w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
            <div className="flex-1 overflow-y-auto p-5 space-y-2">
              ${vars.length === 0 ? html`<div className="text-[12px] text-ink-500 text-center py-4">No variables yet. Click "Add variable" below.</div>` : null}
              ${vars.map((v, i) => html`
                <div key=${i} className="flex items-center gap-2">
                  <input value=${v.name} onChange=${e => onChange(i, 'name', e.target.value)} placeholder="name" className="w-[140px] px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                  <input value=${v.desc || ''} onChange=${e => onChange(i, 'desc', e.target.value)} placeholder="description" className="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  <input value=${v.default || ''} onChange=${e => onChange(i, 'default', e.target.value)} placeholder="default" className="w-[120px] px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                  <button onClick=${() => onRemove(i)} className="w-9 h-9 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
                </div>`)}
            </div>
            <div className="px-5 py-3 border-t border-paper-200 flex items-center justify-between bg-paper-50/40">
              <button onClick=${onAdd} className="text-[12px] text-wa-deep font-semibold hover:underline inline-flex items-center gap-1"><${Icon} d="M8 3v10M3 8h10" className="w-3 h-3" />Add variable</button>
              <button onClick=${onClose} className="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">Done</button>
            </div>
          </div>
        </div>
      `;
    }

    function TestPanel({ open, onClose, log, onSend, awaiting, onRestart }) {
      const [val, setVal] = useState('');
      const logRef = useRef(null);
      useEffect(() => { if (logRef.current) logRef.current.scrollTop = logRef.current.scrollHeight; }, [log]);
      if (!open) return null;
      const send = () => { if (!val.trim()) return; onSend(val); setVal(''); };
      return html`
        <div className="fixed bottom-6 right-6 w-[380px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft z-40 flex flex-col" style=${{ maxHeight:'65vh' }}>
          <div className="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="w-7 h-7 rounded-full bg-wa-mint text-wa-deep grid place-items-center"><svg viewBox="0 0 16 16" className="w-3.5 h-3.5" fill="currentColor"><path d="M5 3l8 5-8 5z"/></svg></span>
              <div>
                <div className="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Test runner</div>
                <div className="font-serif text-[15px] leading-tight">Simulate a chat</div>
              </div>
            </div>
            <div className="flex items-center gap-1">
              <button onClick=${onRestart} className="text-[11px] text-wa-deep font-semibold hover:underline px-1.5">Restart</button>
              <button onClick=${onClose} className="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center"><${Icon} d="M4 4l8 8M12 4l-8 8" className="w-3.5 h-3.5" /></button>
            </div>
          </div>
          <div ref=${logRef} className="flex-1 overflow-y-auto p-3 space-y-2 bg-paper-50/40 flex flex-col">
            ${log.map((m, i) => {
              if (m.kind === 'system') return html`<div key=${i} className="text-center text-[10.5px] font-mono text-ink-500 italic py-1">${m.text}</div>`;
              if (m.kind === 'user') return html`<div key=${i} className="max-w-[80%] px-3 py-2 rounded-lg text-[12.5px] whitespace-pre-wrap leading-snug bg-wa-mint self-end rounded-br-[4px]">${m.text}</div>`;
              // Bot bubble. If a media payload is attached, render the
              // actual <img>/<video>/<audio>/document chip — same look
              // WhatsApp gives the customer when the Node runtime sends.
              const media = m.media;
              const bubble = html`<div key=${i} className="max-w-[80%] flex flex-col gap-1.5 px-2.5 py-2 rounded-lg bg-paper-0 border border-paper-200 self-start rounded-bl-[4px]">
                ${media && media.url ? (
                  media.kind === 'image'    ? html`<img src=${media.url} alt=${media.filename || ''} className="rounded max-h-[200px] w-auto object-contain bg-paper-100" onError=${e => { e.currentTarget.style.display='none'; }} />` :
                  media.kind === 'video'    ? html`<video src=${media.url} controls className="rounded max-h-[220px] w-full bg-paper-100"></video>` :
                  media.kind === 'audio'    ? html`<audio src=${media.url} controls className="w-full"></audio>` :
                                              html`<a href=${media.url} target="_blank" rel="noopener" className="flex items-center gap-2 px-2.5 py-2 rounded border border-paper-200 bg-paper-50 hover:bg-paper-100 text-[11.5px] no-underline text-ink-700">
                                                <${Icon} d="M4 2h6l2 2v10H4zM6 6h4M6 9h4M6 12h3" className="w-3.5 h-3.5 text-wa-deep" />
                                                <span className="truncate">${media.filename || media.url.split('/').pop()}</span>
                                              </a>`
                ) : null}
                ${m.text ? html`<div className="text-[12.5px] whitespace-pre-wrap leading-snug px-1">${m.text}</div>` : null}
              </div>`;
              return bubble;
            })}
          </div>
          <div className="p-3 border-t border-paper-200 flex items-center gap-2">
            <input value=${val} onChange=${e => setVal(e.target.value)} onKeyDown=${e => e.key === 'Enter' && send()} placeholder=${awaiting ? 'Type a reply…' : 'Waiting for bot…'} disabled=${!awaiting} className="flex-1 px-3 py-2 border border-paper-200 rounded-full bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 disabled:bg-paper-50 disabled:cursor-not-allowed" />
            <button onClick=${send} disabled=${!awaiting} className="px-3.5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold disabled:opacity-50">Send</button>
          </div>
        </div>
      `;
    }

    /* floating action bar (pin / pencil / delete) attached to selected node */
    function NodeActionBar({ node, pan, zoom, onEdit, onDelete, onPin }) {
      /* position over the node center, above the colored header */
      const screenX = pan.x + (node.x + NODE_W/2) * zoom;
      const screenY = pan.y + (node.y - 14) * zoom;
      const pinned = !!node.isStart;
      return html`
        <div className="node-actions" style=${{ left: screenX, top: screenY, transform: 'translate(-50%, -100%)' }} onMouseDown=${(e) => e.stopPropagation()}>
          <button onClick=${onPin} title=${pinned ? 'This is the start point (click to unpin)' : 'Make this the start point'} style=${pinned ? { background:themeColor('wa-mint'), color:themeColor('wa-deep') } : null}>
            <svg viewBox="0 0 16 16" className="w-4 h-4" fill=${pinned ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
              <path d="M8 1.5l1.9 4 4.1.5-3 2.9.8 4-3.8-2-3.8 2 .8-4-3-2.9 4.1-.5z"/>
            </svg>
          </button>
          <button onClick=${onEdit} title="Edit (open inspector)">
            <svg viewBox="0 0 16 16" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M11 2.5l2.5 2.5L5 13.5H2.5V11z"/><path d="M9.5 4l2.5 2.5"/></svg>
          </button>
          <button className="danger" onClick=${onDelete} title="Delete (⌫)">
            <svg viewBox="0 0 16 16" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9"/></svg>
          </button>
        </div>
      `;
    }

    /* minimap — bottom-right small overview with draggable viewport rectangle */
    function Minimap({ nodes, edges, pan, zoom, setPan, wrapperRef }) {
      const W = 170, H = 110, PAD = 8;
      const bbox = useMemo(() => {
        if (!nodes.length) return { minX:0, minY:0, maxX:600, maxY:400 };
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        nodes.forEach(n => {
          minX = Math.min(minX, n.x); minY = Math.min(minY, n.y);
          maxX = Math.max(maxX, n.x + NODE_W); maxY = Math.max(maxY, n.y + 100);
        });
        minX -= 60; minY -= 60; maxX += 60; maxY += 60;
        return { minX, minY, maxX, maxY };
      }, [nodes]);
      const bw = Math.max(1, bbox.maxX - bbox.minX);
      const bh = Math.max(1, bbox.maxY - bbox.minY);
      const scale = Math.min((W - PAD*2)/bw, (H - PAD*2)/bh);
      const offX = PAD + (W - PAD*2 - bw*scale)/2;
      const offY = PAD + (H - PAD*2 - bh*scale)/2;
      /* viewport rect (in world coords) */
      const wr = wrapperRef.current ? wrapperRef.current.getBoundingClientRect() : { width: 1000, height: 600 };
      const vx = -pan.x / zoom, vy = -pan.y / zoom;
      const vw = wr.width / zoom, vh = wr.height / zoom;

      const project = (x, y) => ({ x: offX + (x - bbox.minX) * scale, y: offY + (y - bbox.minY) * scale });
      const v = project(vx, vy);
      const draggingRef = useRef(null);

      const onDown = (e) => {
        e.preventDefault(); e.stopPropagation();
        draggingRef.current = { startX: e.clientX, startY: e.clientY, startPanX: pan.x, startPanY: pan.y };
      };
      useEffect(() => {
        const onMove = (ev) => {
          if (!draggingRef.current) return;
          const dx = (ev.clientX - draggingRef.current.startX) / scale;
          const dy = (ev.clientY - draggingRef.current.startY) / scale;
          setPan({ x: draggingRef.current.startPanX - dx * zoom, y: draggingRef.current.startPanY - dy * zoom });
        };
        const onUp = () => { draggingRef.current = null; };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        return () => { window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp); };
      }, [scale, zoom, setPan]);

      return html`
        <div className="minimap" onMouseDown=${(e) => e.stopPropagation()}>
          <svg viewBox=${`0 0 ${W} ${H}`}>
            ${nodes.map(n => {
              const p = project(n.x, n.y);
              return html`<rect key=${n.id} x=${p.x} y=${p.y} width=${Math.max(2, NODE_W*scale)} height=${Math.max(2, 60*scale)} rx="2" className="mm-node" />`;
            })}
            <rect x=${v.x} y=${v.y} width=${Math.max(2, vw*scale)} height=${Math.max(2, vh*scale)} className="mm-view" onMouseDown=${onDown} />
          </svg>
        </div>
      `;
    }

    // Pull initial state from the #root data-* attributes set by the
    // blade. When editing an existing flow, the server hands us the
    // flow_id + decoded flow_data so the builder can hydrate.
    const ROOT_EL = document.getElementById('root');
    const ROOT_DS = ROOT_EL?.dataset || {};
    const INITIAL_FLOW_ID    = ROOT_DS.flowId ? Number(ROOT_DS.flowId) : null;
    const INITIAL_FLOW_NAME  = ROOT_DS.flowName || 'New flow';
    const INITIAL_PUBLISHED  = ROOT_DS.flowPublished === '1';
    const INITIAL_CATEGORY   = ROOT_DS.flowCategory || '';
    let INITIAL_FLOW_JSON = { flowNodes: [], flowEdges: [] };
    try { if (ROOT_DS.flowJson) INITIAL_FLOW_JSON = JSON.parse(ROOT_DS.flowJson); } catch (e) { /* keep defaults */ }

    function csrfToken() {
        return document.querySelector('meta[name=csrf-token]')?.content || '';
    }

    async function postJSON(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || ('HTTP ' + res.status));
        }
        return data;
    }

    function FlowApp() {
      // New flow opens with a clean canvas — no demo data. The
      // palette on the left is the affordance for adding the first
      // node. Editing an existing flow loads its saved nodes/edges.
      const seedData = useMemo(() => {
          const rawN = Array.isArray(INITIAL_FLOW_JSON.flowNodes) ? INITIAL_FLOW_JSON.flowNodes : [];
          const rawE = Array.isArray(INITIAL_FLOW_JSON.flowEdges) ? INITIAL_FLOW_JSON.flowEdges : [];
          // Normalize exactly like the AI-apply path (backfill id/type/x/y/data)
          // so a loaded or CLONED flow whose nodes are missing coordinates —
          // e.g. an admin Bot-Flow template pasted/exported without x/y — still
          // RENDERS. Previously the seed used flowNodes raw, so such nodes
          // landed at undefined positions and the canvas looked empty even
          // though the flow had steps ("cloned template opens empty" bug).
          const n = rawN.map((nd, i) => {
              const nx = Number(nd.x), ny = Number(nd.y);
              return {
                  id:   String(nd.id || nid()),
                  type: String(nd.type || 'message'),
                  x:    Number.isFinite(nx) ? nx : (120 + (i % 3) * 260),
                  y:    Number.isFinite(ny) ? ny : (120 + Math.floor(i / 3) * 180),
                  data: { ...defaultData(String(nd.type || 'message')), ...(nd.data || {}) },
                  isStart: !!nd.isStart,
              };
          });
          const validIds = new Set(n.map(x => x.id));
          const e = rawE
              .filter(ed => validIds.has(String(ed.source)) && validIds.has(String(ed.target)))
              .map(ed => ({
                  id:           String(ed.id || eid()),
                  source:       String(ed.source),
                  sourceHandle: String(ed.sourceHandle || 'out'),
                  target:       String(ed.target),
              }));
          // Guarantee exactly one start anchor (trigger, else first node).
          if (n.length && !n.some(x => x.isStart)) {
              const ti = n.findIndex(x => x.type === 'trigger');
              n[ti >= 0 ? ti : 0].isStart = true;
          }
          return { nodes: n, edges: e };
      }, []);
      const [nodes, setNodes] = useState(seedData.nodes);
      const [edges, setEdges] = useState(seedData.edges);
      const [flowName, setFlowName] = useState(INITIAL_FLOW_NAME);
      const [flowId, setFlowId]     = useState(INITIAL_FLOW_ID);
      const [category]              = useState(INITIAL_CATEGORY);
      const [status, setStatus] = useState(INITIAL_PUBLISHED ? 'Published' : 'Draft');
      const [paletteQuery, setPaletteQuery] = useState('');
      const [varsOpen, setVarsOpen] = useState(false);
      const [testOpen, setTestOpen] = useState(() => /[?&]test=1\b/.test(window.location.search));
      const [testLog, setTestLog] = useState([]);
      const [testAwaiting, setTestAwaiting] = useState(false);
      const testStateRef = useRef({ vars:{name:'Anya'}, current:null });
      const [vars, setVars] = useState([{ name:'name', desc:'Contact name', default:'' }, { name:'phone', desc:'Phone number', default:'' }]);
      const [insOpen, setInsOpen] = useState(false);
      // Default the node palette OPEN on desktop but CLOSED on phones —
      // on a phone it's an overlay drawer (see .flow-palette CSS) and
      // leaving it open would cover the whole canvas on load.
      const [sidebarOpen, setSidebarOpen] = useState(
        () => !(typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches)
      );
      // Dashboard Copilot card deep-links here with ?ai_prompt=… (or ?ai=1)
      // to open the AI Flow Generator straight away, pre-filled.
      const aiInitialPrompt = (() => {
        try { return new URLSearchParams(window.location.search).get('ai_prompt') || ''; }
        catch (_) { return ''; }
      })();
      const [aiOpen, setAiOpen] = useState(() => /[?&]ai(_prompt)?=/.test(window.location.search));
      const [saveOpen, setSaveOpen] = useState(false);
      const [saveBusy, setSaveBusy] = useState(false);
      // "Create AI agent" modal — opened from the Chatbot node's
      // inspector via the inline + button. When the modal resolves
      // we push the new agent into AGENTS_CACHE and auto-select it.
      const [newAgentOpen, setNewAgentOpen] = useState(false);
      const [newAgentCb,   setNewAgentCb]   = useState(null); // (agent) => void
      // Commerce product picker — opened from the commerce node
      // inspectors. ctx carries the provider + storeId + already-picked
      // retailer ids so we can grey them out, plus an onPicked callback.
      const [productPickerOpen, setProductPickerOpen] = useState(false);
      const [productPickerCtx,  setProductPickerCtx]  = useState(null);
      const [selectedId, setSelectedId] = useState(null);
      const [hoveredEdge, setHoveredEdge] = useState(null);
      const [hoveredPort, setHoveredPort] = useState(null);
      const [pendingFrom, setPendingFrom] = useState(null);
      const [pan, setPan] = useState({ x: 40, y: 40 });
      const [zoom, setZoom] = useState(0.95);
      const [history, setHistory] = useState([JSON.stringify({ nodes: seedData.nodes, edges: seedData.edges })]);
      const [histIdx, setHistIdx] = useState(0);
      const skipHistRef = useRef(false);
      const [toastMsg, setToastMsg] = useState(null);
      const toast = (m) => { setToastMsg(m); setTimeout(() => setToastMsg(null), 1800); };

      // Pre-save flow validation. `invalidNodes` = the ids we outline in red;
      // `validationError` = the red popup that lists WHY the save was blocked.
      // Both reset whenever the wiring changes (the [edges] effect below) so a
      // fixed node's red border clears the moment it's connected.
      const [invalidNodes, setInvalidNodes]       = useState(() => new Set());
      const [validationError, setValidationError] = useState(null);
      useEffect(() => { setInvalidNodes(new Set()); setValidationError(null); }, [edges]);

      // Workspace's approved templates + saved flows. Loaded once on
      // mount; the module-level CACHES stay in sync so renderPreview
      // (outside React) and the Inspector dropdowns can read them.
      // `tplVer` is a tick we bump on load so dependent components
      // re-render once the data arrives.
      const [tplVer, setTplVer] = useState(0);
      useEffect(() => {
        let cancelled = false;
        const fetchJson = (url) => fetch(url, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        }).then(r => r.ok ? r.json() : null).catch(() => null);

        Promise.all([
          fetchJson('/templates/api/list?type=standard'),
          fetchJson('/flows/api/list'),
          fetchJson('/flows/api/ai-models'),
          fetchJson('/team-inbox/api/teams'),
          fetchJson('/team-inbox/api/tags'),
          fetchJson('/team-inbox/api/ai-agents'),
          fetchJson('/deals/stages'),
          fetchJson('/flows/api/ai-assistants'),
          fetchJson('/flows/api/call-assistants'),
        ]).then(([tplJson, flowJson, aiJson, teamsJson, tagsJson, agentsJson, stagesJson, assistantsJson, callAsstJson]) => {
          if (cancelled) return;
          if (tplJson  && Array.isArray(tplJson.templates))  TEMPLATES_CACHE = tplJson.templates;
          if (flowJson && Array.isArray(flowJson.data))      FLOWS_CACHE     = flowJson.data;
          if (aiJson   && Array.isArray(aiJson.models))      AI_MODELS_CACHE = aiJson.models;
          if (assistantsJson && Array.isArray(assistantsJson.assistants)) AI_ASSISTANTS_CACHE = assistantsJson.assistants;
          if (callAsstJson && Array.isArray(callAsstJson.assistants)) AI_CALL_ASSISTANTS_CACHE = callAsstJson.assistants;
          if (Array.isArray(teamsJson))                      TEAMS_CACHE     = teamsJson;
          if (Array.isArray(tagsJson))                       TAGS_CACHE      = tagsJson;
          if (Array.isArray(agentsJson))                     AGENTS_CACHE    = agentsJson;
          if (stagesJson && Array.isArray(stagesJson.data))  STAGES_CACHE    = stagesJson.data;
          setTplVer(v => v + 1);
        });
        return () => { cancelled = true; };
      }, []);

      const portOffsetsRef = useRef({});
      const [portVer, setPortVer] = useState(0);

      const wrapperRef = useRef(null);
      const draggingNodeRef = useRef(null);
      const panRef = useRef(null);
      const [tempEdge, setTempEdge] = useState(null);
      const [draggingId, setDraggingId] = useState(null);

      const selectedNode = useMemo(() => nodes.find(n => n.id === selectedId) || null, [nodes, selectedId]);
      /* don't auto-open inspector — user opens via pencil icon */

      const measurePorts = useCallback(() => {
        const next = {};
        document.querySelectorAll('[data-port]').forEach(el => {
          const card = el.closest('[data-node-id]');
          if (!card) return;
          const cr = card.getBoundingClientRect();
          const er = el.getBoundingClientRect();
          if (cr.width === 0 || er.width === 0) return;
          const cx = (er.left + er.width/2 - cr.left) / zoom;
          const cy = (er.top + er.height/2 - cr.top) / zoom;
          next[el.getAttribute('data-port')] = { x: cx, y: cy };
        });
        portOffsetsRef.current = next;
        setPortVer(v => v + 1);
      }, [zoom]);

      useLayoutEffect(() => { measurePorts(); }, [nodes, edges, zoom, measurePorts]);

      /* re-measure after Tailwind/fonts settle so initial edges render correctly */
      useEffect(() => {
        const t1 = requestAnimationFrame(measurePorts);
        const t2 = setTimeout(measurePorts, 80);
        const t3 = setTimeout(measurePorts, 300);
        const onReady = () => measurePorts();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(onReady);
        window.addEventListener('load', onReady);
        return () => { cancelAnimationFrame(t1); clearTimeout(t2); clearTimeout(t3); window.removeEventListener('load', onReady); };
      }, [measurePorts]);

      /* edge delete popup */
      const [edgePopup, setEdgePopup] = useState(null); // { id, x, y } in world coords

      const pushHistory = useCallback((newNodes, newEdges) => {
        if (skipHistRef.current) return;
        setHistory(prev => {
          const next = prev.slice(0, histIdx + 1);
          next.push(JSON.stringify({ nodes: newNodes, edges: newEdges }));
          return next.length > 60 ? next.slice(next.length - 60) : next;
        });
        setHistIdx(idx => Math.min(idx + 1, 59));
      }, [histIdx]);

      const undo = useCallback(() => {
        if (histIdx <= 0) return;
        const idx = histIdx - 1;
        const d = JSON.parse(history[idx]);
        skipHistRef.current = true;
        setNodes(d.nodes); setEdges(d.edges); setHistIdx(idx);
        setTimeout(() => { skipHistRef.current = false; }, 60);
      }, [histIdx, history]);

      const redo = useCallback(() => {
        if (histIdx >= history.length - 1) return;
        const idx = histIdx + 1;
        const d = JSON.parse(history[idx]);
        skipHistRef.current = true;
        setNodes(d.nodes); setEdges(d.edges); setHistIdx(idx);
        setTimeout(() => { skipHistRef.current = false; }, 60);
      }, [histIdx, history]);

      const updateNodeData = useCallback((id, key, value) => {
        setNodes(curr => {
          const next = curr.map(n => n.id === id ? { ...n, data: { ...n.data, [key]: value } } : n);
          pushHistory(next, edges);
          return next;
        });
        setStatus('Draft · unsaved');
      }, [edges, pushHistory]);

      const addNode = useCallback((type, x, y) => {
        const t = NTYPES[type]; if (!t) return;
        if (t.singleton && nodes.some(n => n.type === type)) { toast(t.label + ' can only exist once.'); return; }
        const id = nid();
        // First node on the canvas auto-becomes the start point so a
        // freshly-created flow always has somewhere to begin. Operator
        // can still re-pin via the action bar.
        const isFirst = nodes.length === 0;
        const n = { id, type, x, y, data: defaultData(type), ...(isFirst ? { isStart: true } : {}) };
        setNodes(curr => { const next = curr.concat(n); pushHistory(next, edges); return next; });
        setSelectedId(id);
        setStatus('Draft · unsaved');
      }, [nodes, edges, pushHistory]);

      const pinNodeAsStart = useCallback((id) => {
        setNodes(curr => {
          const next = curr.map(n => ({ ...n, isStart: n.id === id ? !n.isStart : false }));
          pushHistory(next, edges);
          return next;
        });
        setStatus('Draft · unsaved');
      }, [edges, pushHistory]);

      const addNodeAtCenter = useCallback((type) => {
        if (!wrapperRef.current) return;
        const r = wrapperRef.current.getBoundingClientRect();
        const x = (r.width/2 - pan.x) / zoom - NODE_W/2;
        const y = (r.height/2 - pan.y) / zoom - 60;
        addNode(type, x, y);
      }, [addNode, pan, zoom]);

      const onDropNode = useCallback((e) => {
        e.preventDefault();
        const type = e.dataTransfer.getData('node-type');
        if (!type || !wrapperRef.current) return;
        const r = wrapperRef.current.getBoundingClientRect();
        const x = (e.clientX - r.left - pan.x) / zoom - NODE_W/2;
        const y = (e.clientY - r.top  - pan.y) / zoom - 30;
        addNode(type, x, y);
      }, [addNode, pan, zoom]);

      const deleteNodeById = useCallback((id) => {
        const newEdges = edges.filter(e => e.source !== id && e.target !== id);
        const newNodes = nodes.filter(n => n.id !== id);
        setNodes(newNodes); setEdges(newEdges); pushHistory(newNodes, newEdges);
        if (selectedId === id) setSelectedId(null);
        setStatus('Draft · unsaved');
      }, [edges, nodes, pushHistory, selectedId]);

      const deleteEdge = useCallback((id) => {
        const next = edges.filter(e => e.id !== id);
        setEdges(next); pushHistory(nodes, next);
        setStatus('Draft · unsaved');
      }, [edges, nodes, pushHistory]);

      const onConnect = useCallback((from, targetId) => {
        if (!from || !targetId || from.nodeId === targetId) return;
        setEdges(curr => {
          const filtered = curr.filter(e => !(e.source === from.nodeId && e.sourceHandle === from.handleId && e.target === targetId));
          const newEdge = { id: eid(), source: from.nodeId, sourceHandle: from.handleId, target: targetId, kind: from.handleId === 'no' ? 'no' : null };
          const next = filtered.concat(newEdge);
          pushHistory(nodes, next);
          return next;
        });
        setStatus('Draft · unsaved');
      }, [nodes, pushHistory]);

      const dragStartPosRef = useRef({});
      const onNodeMouseDown = (e, id) => {
        if (e.button !== 0) return;
        setSelectedId(id);
        const r = wrapperRef.current.getBoundingClientRect();
        const wx = (e.clientX - r.left - pan.x) / zoom;
        const wy = (e.clientY - r.top  - pan.y) / zoom;
        const n = nodes.find(x => x.id === id);
        if (!n) return;
        draggingNodeRef.current = { id, offX: wx - n.x, offY: wy - n.y };
        dragStartPosRef.current[id] = { x: n.x, y: n.y };
        setDraggingId(id);
      };
      const onPortMouseDown = useCallback((e, nodeId, handleId) => {
        setPendingFrom({ nodeId, handleId });
      }, []);

      useEffect(() => {
        const onMove = (e) => {
          if (draggingNodeRef.current) {
            const { id, offX, offY } = draggingNodeRef.current;
            const r = wrapperRef.current.getBoundingClientRect();
            const wx = (e.clientX - r.left - pan.x) / zoom - offX;
            const wy = (e.clientY - r.top  - pan.y) / zoom - offY;
            setNodes(curr => curr.map(n => n.id === id ? { ...n, x: wx, y: wy } : n));
            return;
          }
          if (panRef.current) {
            setPan({ x: panRef.current.panX + (e.clientX - panRef.current.startX), y: panRef.current.panY + (e.clientY - panRef.current.startY) });
            return;
          }
          if (pendingFrom) {
            const r = wrapperRef.current.getBoundingClientRect();
            const wx = (e.clientX - r.left - pan.x) / zoom;
            const wy = (e.clientY - r.top  - pan.y) / zoom;
            setTempEdge({ x: wx, y: wy });
          }
        };
        const onUp = (e) => {
          if (draggingNodeRef.current) {
            const id = draggingNodeRef.current.id;
            const start = dragStartPosRef.current[id];
            const n = nodes.find(x => x.id === id);
            if (start && n && (start.x !== n.x || start.y !== n.y)) { pushHistory(nodes, edges); setStatus('Draft · unsaved'); }
            delete dragStartPosRef.current[id];
            draggingNodeRef.current = null;
            setDraggingId(null);
          }
          if (panRef.current) panRef.current = null;
          if (pendingFrom) {
            const el = document.elementFromPoint(e.clientX, e.clientY);
            const portEl = el && el.closest('[data-port]');
            if (portEl) {
              const data = portEl.getAttribute('data-port');
              const parts = data.split(':');
              if (parts[0] === 'in') onConnect(pendingFrom, parts[1]);
            } else {
              const cardEl = el && el.closest('[data-node-id]');
              if (cardEl) onConnect(pendingFrom, cardEl.getAttribute('data-node-id'));
            }
            setPendingFrom(null);
            setTempEdge(null);
          }
        };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        return () => { window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp); };
      }, [pan, zoom, pendingFrom, onConnect, nodes, edges, pushHistory]);

      useEffect(() => {
        const el = wrapperRef.current; if (!el) return;
        const onWheel = (e) => {
          if (!(e.ctrlKey || e.metaKey)) {
            setPan(p => ({ x: p.x - e.deltaX, y: p.y - e.deltaY }));
            e.preventDefault();
            return;
          }
          e.preventDefault();
          const r = el.getBoundingClientRect();
          const mx = e.clientX - r.left, my = e.clientY - r.top;
          const dz = -e.deltaY * 0.0015;
          const nz = Math.max(0.4, Math.min(1.8, zoom * (1 + dz)));
          const wx = (mx - pan.x) / zoom, wy = (my - pan.y) / zoom;
          setPan({ x: mx - wx * nz, y: my - wy * nz });
          setZoom(nz);
        };
        el.addEventListener('wheel', onWheel, { passive:false });
        return () => el.removeEventListener('wheel', onWheel);
      }, [pan, zoom]);

      const onCanvasMouseDown = (e) => {
        if (e.button !== 0) return;
        if (e.target !== e.currentTarget && !e.target.classList.contains('canvas-empty')) return;
        panRef.current = { startX: e.clientX, startY: e.clientY, panX: pan.x, panY: pan.y };
      };
      const onCanvasClick = (e) => {
        if (e.target === e.currentTarget || e.target.classList.contains('canvas-empty')) {
          setSelectedId(null);
          setEdgePopup(null);
        }
      };

      useEffect(() => {
        const handler = (e) => {
          if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
          if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
          else if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) { e.preventDefault(); redo(); }
          else if ((e.key === 'Backspace' || e.key === 'Delete') && selectedId) { e.preventDefault(); deleteNodeById(selectedId); }
          else if (e.key === 'Escape') { setPendingFrom(null); setSelectedId(null); }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
      }, [undo, redo, selectedId, deleteNodeById]);

      const interp = (s, vars) => String(s||'').replace(/\{\{(\w+)\}\}/g, (_, k) => vars[k] ?? `{{${k}}}`);
      // Must agree with the runtime evaluator (_flowEvalCondition in
      // node/services/flowService.js) or the Test preview lies about which
      // port will fire live.
      const checkCond = (v, op, val) => {
        switch (op) {
          case 'equals': return String(v) === String(val);
          case 'not_equals': return String(v) !== String(val);
          case 'contains': return String(v).toLowerCase().includes(String(val).toLowerCase());
          case 'not_contains': return !String(v).toLowerCase().includes(String(val).toLowerCase());
          case 'gt': return parseFloat(v) > parseFloat(val);
          case 'lt': return parseFloat(v) < parseFloat(val);
          case 'exists': return v !== undefined && v !== null && String(v).trim() !== '';
        }
        return false;
      };
      const stepTest = (node) => {
        if (!node) { setTestLog(prev => prev.concat({ kind:'system', text:'Flow ended.' })); testStateRef.current.current = null; setTestAwaiting(false); return; }
        testStateRef.current.current = node;
        const advance = (sourceHandle) => {
          const e = edges.find(x => x.source === node.id && (sourceHandle ? x.sourceHandle === sourceHandle : (!x.sourceHandle || x.sourceHandle === 'out' || x.sourceHandle === 'p0')));
          const next = e ? nodes.find(n => n.id === e.target) : null;
          if (!next) {
            const portLabel = sourceHandle && sourceHandle !== 'out' ? ` (port "${sourceHandle}")` : '';
            setTestLog(prev => prev.concat({ kind:'system', text: `No path forward${portLabel} — stop.` }));
            testStateRef.current.current = null; setTestAwaiting(false); return;
          }
          setTimeout(() => stepTest(next), 220);
        };
        const d = node.data || {};
        const v = testStateRef.current.vars;
        switch (node.type) {
          case 'trigger': return advance('out');
          case 'message': setTestLog(prev => prev.concat({ kind:'bot', text: interp(d.text, v) })); return advance('out');
          case 'sequence': {
            const reps = Array.isArray(d.replies) ? d.replies : [];
            if (reps.length === 0) { setTestLog(prev => prev.concat({ kind:'bot', text: '(empty sequence)' })); return advance('out'); }
            reps.forEach(r => {
              const kind = (r.type || 'text').toLowerCase();
              if (kind === 'text') {
                setTestLog(prev => prev.concat({ kind:'bot', text: interp(r.text || '', v) }));
              } else {
                // Media reply — render as actual preview, not "[image: foo]"
                // text. The Node runtime sends WhatsApp media bubbles;
                // the test runner should match that look.
                setTestLog(prev => prev.concat({
                  kind: 'bot',
                  text: r.caption ? interp(r.caption, v) : '',
                  media: {
                    kind: kind,                                        // image | video | audio | document
                    url:  r.url || r.data || '',                       // absolute or /uploads/... path
                    filename: r.filename || '',
                  },
                }));
              }
            });
            return advance('out');
          }
          case 'template': {
            const qr = templateQrButtons(node);
            const head = '[template: ' + (d.tpl||'?') + ']\n' + interp(d.preview||'', v);
            if (qr.length) {
              // Mirror the Node runtime: quick-reply buttons pause the flow
              // and each branches to its own next node.
              setTestLog(prev => prev.concat({ kind:'bot', text: head + '\n' + qr.map((b,i)=>`  ${i+1}. ${b.text||b.title||b.label||('Button '+(i+1))}`).join('\n') }));
              setTestAwaiting(true);
              return;
            }
            setTestLog(prev => prev.concat({ kind:'bot', text: head }));
            return advance('out');
          }
          case 'media': {
            const kind = (d.kind || 'image').toLowerCase();
            setTestLog(prev => prev.concat({
              kind: 'bot',
              text: d.caption ? interp(d.caption, v) : '',
              media: { kind, url: d.url || d.data || '', filename: d.filename || '' },
            }));
            return advance('out');
          }
          case 'buttons':
          case 'list':
          case 'poll':
            setTestLog(prev => prev.concat({ kind:'bot', text: interp(d.prompt || d.question || '', v) + '\n' + (d.options||[]).map((o,i)=>{
              if (typeof o === 'string') return `  ${i+1}. ${o}`;
              const it = listOpt(o, i);
              return `  ${i+1}. ${it.title}${it.description ? ' — ' + it.description : ''}${it.section ? '   [' + it.section + ']' : ''}`;
            }).join('\n') }));
            setTestAwaiting(true);
            return;
          case 'ask':
            setTestLog(prev => prev.concat({ kind:'bot', text: interp(d.prompt, v) }));
            setTestAwaiting(true);
            return;
          case 'condition': {
            // Walk the AND/OR chain. Each rule is evaluated against the
            // current test variables; ops[i-1] joins rule i-1 → i.
            // Legacy single-condition flows (var/op/value) still work.
            const conds = Array.isArray(d.conditions) ? d.conditions
                        : (d.var ? [{ variable: d.var, operator: d.op, value: d.value }] : []);
            const ops = Array.isArray(d.operators) ? d.operators : [];
            let ok = false;
            if (conds.length === 0) {
              ok = false;
            } else {
              ok = checkCond(v[conds[0].variable] ?? '', conds[0].operator, conds[0].value);
              for (let i = 1; i < conds.length; i++) {
                const next = checkCond(v[conds[i].variable] ?? '', conds[i].operator, conds[i].value);
                ok = (ops[i - 1] || 'AND') === 'OR' ? (ok || next) : (ok && next);
              }
            }
            const summary = conds.map((c, i) => `${i ? ' ' + (ops[i-1] || 'AND') + ' ' : ''}${c.variable} ${c.operator} ${c.value}`).join('');
            setTestLog(prev => prev.concat({ kind:'system', text: `if ${summary} → ${ok ? 'YES' : 'NO'}` }));
            return advance(ok ? 'yes' : 'no');
          }
          case 'delay':   setTestLog(prev => prev.concat({ kind:'system', text:`wait ${d.amount} ${d.unit} (skipped in test)` })); return advance('out');
          case 'webhook': {
            // Fire the REAL request server-side (SSRF-guarded) so the operator
            // can confirm their endpoint is actually hit from the Test Runner —
            // same substitution + headers the live Node runtime uses. The
            // response is saved to {{save}} (and flattened to {{save.field}})
            // so downstream nodes resolve it in the test too.
            const wUrl = interp(d.url || '', v);
            if (!String(wUrl).trim()) {
              setTestLog(prev => prev.concat({ kind:'system', text:'webhook has no URL — skipped' }));
              return advance('out');
            }
            const saveAs = d.save || d.variable || 'response';
            setTestLog(prev => prev.concat({ kind:'system', text:`Calling ${(d.method||'POST')} ${wUrl} …` }));
            fetch(APP_BASE + '/flows/api/test-webhook', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
              },
              credentials: 'same-origin',
              body: JSON.stringify({
                method: d.method, url: d.url, body: d.body,
                contentType: d.contentType, headers: d.headers, vars: v,
              }),
            })
              .then(r => r.json())
              .then(j => {
                if (j && j.ok) {
                  v[saveAs] = typeof j.response === 'string' ? j.response : JSON.stringify(j.response ?? '');
                  if (j.json && typeof j.json === 'object') {
                    Object.keys(j.json).forEach(k => { v[`${saveAs}.${k}`] = typeof j.json[k] === 'object' ? JSON.stringify(j.json[k]) : String(j.json[k]); });
                  }
                  setTestLog(prev => prev.concat({ kind:'system', text:`← HTTP ${j.status}${j.success ? '' : ' (non-2xx)'} · saved to {{${saveAs}}}` }));
                } else {
                  v[saveAs] = '';
                  setTestLog(prev => prev.concat({ kind:'system', text:`webhook failed: ${(j && j.error) || 'no response'}` }));
                }
              })
              .catch(e => {
                v[saveAs] = '';
                setTestLog(prev => prev.concat({ kind:'system', text:`webhook error: ${e.message}` }));
              })
              .finally(() => {
                // Only continue if this run wasn't restarted mid-flight.
                if (testStateRef.current.current === node) advance('out');
              });
            return; // async — advance happens once the request settles
          }
          case 'code':    setTestLog(prev => prev.concat({ kind:'system', text:`run JS → {{${d.save || 'result'}}} (mocked — runs live only)` })); return advance('out');
          case 'mysql':   setTestLog(prev => prev.concat({ kind:'system', text:`query ${d.database || 'db'}: ${(d.sql || '').slice(0, 48)}… (mocked)` })); return advance('out');
          case 'ai':      setTestLog(prev => prev.concat({ kind:'bot', text: '[' + (d.model || 'AI') + '] (simulated reply)' })); return advance('out');
          case 'tag':     setTestLog(prev => prev.concat({ kind:'system', text:`${d.action || 'add'} tag "${d.tag || d.tagId || '?'}"` })); return advance('out');
          case 'assign':  setTestLog(prev => prev.concat({ kind:'system', text:`assigned to team "${d.team || '?'}"${d.userId ? ' user ' + d.userId : ''}` })); return advance('out');
          case 'subflow': setTestLog(prev => prev.concat({ kind:'system', text:`run sub-flow ${d.flow || '?'} (skipped in test)` })); return advance('out');
          case 'cta': {
            const actions = Array.isArray(d.actions) ? d.actions : [];
            const lines = actions.length
              ? actions.map(a => `[${a.type || 'url'}] ${a.label || ''} → ${a.value || ''}`).join('\n')
              : `[${d.kind || 'url'}] ${d.label || ''} → ${d.url || d.phone || d.code || ''}`;
            setTestLog(prev => prev.concat({ kind:'bot', text:`(CTA)\n${lines}` }));
            return advance('out');
          }
          case 'location':
            setTestLog(prev => prev.concat({ kind:'bot', text:`(location) ${d.name || ''}\n${d.address || ''}${(d.lat && d.lng) ? `\nlat ${d.lat}, lng ${d.lng}` : ''}` }));
            return advance('out');
          case 'chatbot':
            setTestLog(prev => prev.concat({ kind:'bot', text:`[chatbot: ${d.bot || '?'}] (simulated)` }));
            return advance('out');
          case 'whatsapp_shop':
          case 'woocommerce':
          case 'shopify': {
            const items = Array.isArray(d.productItems) ? d.productItems : [];
            if (items.length === 0) {
              setTestLog(prev => prev.concat({ kind:'system', text:'No products on commerce node — taking abandoned port.' }));
              return advance('abandoned');
            }
            const provider = node.type;
            const list = items.map((p, i) => `  ${i+1}. ${p.name || p.retailer_id}${p.price_minor ? ` · ${(p.price_minor/100).toFixed(2)} ${p.currency || ''}` : ''}`).join('\n');
            setTestLog(prev => prev.concat({ kind:'bot', text: `[${provider}] ${interp(d.bodyText || 'Pick a product:', v)}\n${list}\n(Reply with a number to simulate purchase, or wait — abandon timer not run in test)` }));
            setTestAwaiting(true);
            return;
          }
          case 'book_appointment': {
            const sample = ['Mon 20 May · 10:00 AM','Mon 20 May · 11:30 AM','Tue 21 May · 9:00 AM','Tue 21 May · 4:00 PM','Wed 22 May · 2:30 PM'];
            const slots = sample.slice(0, Math.max(1, Math.min(10, +d.slotCount || 5)));
            setTestLog(prev => prev.concat({ kind:'bot', text:(d.prompt || 'Pick a time:') + '\n' + slots.map((s,i)=>`  ${i+1}. ${s}`).join('\n') }));
            setTestLog(prev => prev.concat({ kind:'system', text:'(simulated) customer picks slot 1 → booked' }));
            return advance('booked');
          }
          case 'google_sheets': {
            const summary = d.mode === 'read'
              ? `Lookup row where ${d.matchColumn||'?'}="${interp(d.matchValue||'',v)}" in ${d.sheetId ? String(d.sheetId).slice(0,10)+'…' : '(no sheet)'}`
              : `Append row to ${d.sheetId ? String(d.sheetId).slice(0,10)+'…' : '(no sheet)'} — ${(d.columns||[]).length} columns`;
            setTestLog(prev => prev.concat({ kind:'system', text:`[Google Sheets · ${(d.mode||'write').toUpperCase()}] ${summary} (skipped in test)` }));
            return advance('out');
          }
          case 'google_docs': {
            setTestLog(prev => prev.concat({ kind:'system', text:`[Google Docs] copy template ${d.templateId ? String(d.templateId).slice(0,10)+'…' : '(no template)'} → "${interp(d.newTitle||'',v)}" (mocked URL)` }));
            const mockUrl = 'https://docs.google.com/document/d/MOCKED/edit';
            setTestLog(prev => prev.concat({ kind:'bot', text: interp(d.messageTemplate||"Here's your document: {{doc_url}}", { ...v, doc_url: mockUrl }) }));
            return advance('out');
          }
          case 'google_form': {
            const mockUrl = `https://docs.google.com/forms/d/${d.formId || 'MOCKED'}/viewform`;
            setTestLog(prev => prev.concat({ kind:'bot', text: interp(d.bodyText||'Please fill out this short form:', v) + '\n\n' + mockUrl }));
            setTestLog(prev => prev.concat({ kind:'system', text:'(simulated) form submitted → submitted port' }));
            return advance('submitted');
          }
          case 'deal': {
            const act = d.action === 'move' ? 'move contact deal to' : 'create deal in';
            setTestLog(prev => prev.concat({ kind:'system', text:`[CRM] ${act} stage ${d.stageId || '(none)'} — "${interp(d.dealName||'deal', v)}"${d.value ? ' · ' + interp(String(d.value), v) : ''} (skipped in test)` }));
            return advance('created');
          }
          case 'end':     setTestLog(prev => prev.concat({ kind:'system', text:'Flow ended.' })); testStateRef.current.current = null; setTestAwaiting(false); return;
          default:
            setTestLog(prev => prev.concat({ kind:'system', text:`(unknown node type "${node.type}" — skipping)` }));
            return advance('out');
        }
      };
      const startTest = useCallback(() => {
        setTestLog([]);
        // Prefer the explicitly-pinned start node (isStart=true). Legacy
        // flows fall back to the first 'trigger'. As a last resort use
        // whatever node 0 is, so a brand-new canvas without a trigger
        // still gives the user SOME feedback rather than a dead panel.
        const start = nodes.find(n => n.isStart)
                   ?? nodes.find(n => n.type === 'trigger')
                   ?? nodes[0];
        if (!start) { setTestLog([{ kind:'system', text:"Canvas is empty. Add a trigger and at least one node, then click Restart." }]); setTestAwaiting(false); return; }
        // Match WhatsApp behaviour: the bot waits for the customer to
        // open the conversation. Park here with `current=null` until
        // the user types their first message; sendTest() detects the
        // null current and kicks off the flow from `start`.
        const trigger = start.type === 'trigger' ? start : null;
        const expected = trigger && trigger.data?.keywords
          ? trigger.data.keywords.split(',').map(s => s.trim()).filter(Boolean)
          : [];
        testStateRef.current = {
          vars: { name: 'Anya', phone: '+919876543210' },
          current: null,
          pendingStart: start,
          expectedKeywords: expected,
        };
        const hint = expected.length
          ? `Type "${expected[0]}" (or any keyword: ${expected.join(', ')}) to start the flow.`
          : 'Type any message to start the flow.';
        setTestLog([{ kind:'system', text: hint }]);
        setTestAwaiting(true);
      }, [nodes, edges]);
      const sendTest = useCallback((value) => {
        const st = testStateRef.current;
        // First message — open the conversation. If the start node is a
        // trigger with keywords, surface a soft warning when the user
        // typed something off-keyword, but still run the flow so they
        // can iterate without restart-typing every time.
        if (!st.current && st.pendingStart) {
          setTestLog(prev => prev.concat({ kind:'user', text: value }));
          const start = st.pendingStart;
          if (Array.isArray(st.expectedKeywords) && st.expectedKeywords.length > 0) {
            const lc = value.toLowerCase();
            const hit = st.expectedKeywords.some(k => lc.includes(k.toLowerCase()));
            if (!hit) {
              setTestLog(prev => prev.concat({ kind:'system', text: `(message didn't match any keyword — running anyway for testing)` }));
            }
          }
          st.vars.user_message = value;     // upstream nodes can reference it
          st.pendingStart      = null;
          setTestAwaiting(false);
          setTimeout(() => stepTest(start), 200);
          return;
        }
        const node = st.current;
        if (!node) return;
        setTestLog(prev => prev.concat({ kind:'user', text: value }));
        if (node.type === 'ask') {
          testStateRef.current.vars[node.data.var || 'answer'] = value;
          setTestAwaiting(false);
          const expected = Array.isArray(node.data.options) ? node.data.options : [];
          let handle = null;
          if (expected.length > 0) {
            const idx = expected.findIndex(o => o && o.toLowerCase() === value.toLowerCase());
            handle = idx >= 0 ? ('p' + idx) : 'else';
          }
          const e = edges.find(x => x.source === node.id && (handle ? x.sourceHandle === handle : true));
          const next = e ? nodes.find(n => n.id === e.target) : null;
          if (next) setTimeout(() => stepTest(next), 220);
          else if (handle) setTestLog(prev => prev.concat({ kind:'system', text: `Port "${handle}" not connected — stop.` }));
          else setTestLog(prev => prev.concat({ kind:'system', text: 'Nothing connected after ask — stop.' }));
        } else if (node.type === 'buttons' || node.type === 'list' || node.type === 'poll') {
          // List items are objects — match (and save) their title, never the
          // object itself, or {{choice}} interpolates as "[object Object]".
          const rawOpts = node.data.options || [];
          const opts    = node.type === 'list' ? rawOpts.map((o, i) => listOpt(o, i).title) : rawOpts.map(o => String(o));
          let idx = opts.findIndex(o => o.toLowerCase() === String(value).toLowerCase());
          if (idx < 0) { const n = parseInt(value, 10); if (!isNaN(n) && n >= 1 && n <= opts.length) idx = n - 1; }
          if (idx < 0) idx = 0;
          testStateRef.current.vars[node.data.var || 'choice'] = opts[idx] || value;
          setTestAwaiting(false);
          const e = edges.find(x => x.source === node.id && x.sourceHandle === 'p' + idx);
          const next = e ? nodes.find(n => n.id === e.target) : null;
          if (next) setTimeout(() => stepTest(next), 220);
          else setTestLog(prev => prev.concat({ kind:'system', text:`Port "p${idx}" (option ${idx+1}) not connected — stop.` }));
        } else if (node.type === 'template') {
          // Match the tap against the template's quick-reply buttons, then
          // route through p<idx> exactly like the Buttons node.
          const qr = templateQrButtons(node);
          let idx = qr.findIndex(b => String(b.text||b.title||b.label||'').toLowerCase() === String(value).toLowerCase());
          if (idx < 0) { const n = parseInt(value, 10); if (!isNaN(n) && n >= 1 && n <= qr.length) idx = n - 1; }
          if (idx < 0) idx = 0;
          setTestAwaiting(false);
          const e = edges.find(x => x.source === node.id && x.sourceHandle === 'p' + idx);
          const next = e ? nodes.find(n => n.id === e.target) : null;
          if (next) setTimeout(() => stepTest(next), 220);
          else setTestLog(prev => prev.concat({ kind:'system', text:`Button "${(qr[idx] && (qr[idx].text||qr[idx].title)) || ('p'+idx)}" not connected — stop.` }));
        } else if (node.type === 'whatsapp_shop' || node.type === 'woocommerce' || node.type === 'shopify') {
          // Commerce simulation: numeric pick = purchase, anything else
          // = treat as abandon. Mirrors how the Node runtime would
          // route through resumePort (purchased=1) or the abandon timer
          // (port 2).
          const items = Array.isArray(node.data.productItems) ? node.data.productItems : [];
          const n = parseInt(value, 10);
          setTestAwaiting(false);
          if (!isNaN(n) && n >= 1 && n <= items.length) {
            const picked = items[n - 1];
            testStateRef.current.vars.commerce_choice = picked.retailer_id;
            testStateRef.current.vars.order_id       = 'TEST-' + Math.floor(Math.random() * 9000 + 1000);
            testStateRef.current.vars.order_total    = String((picked.price_minor || 0) / 100);
            testStateRef.current.vars.order_currency = String(picked.currency || '');
            setTestLog(prev => prev.concat({ kind:'system', text:`Simulated purchase: ${picked.name || picked.retailer_id} → port "purchased"` }));
            const e = edges.find(x => x.source === node.id && x.sourceHandle === 'purchased');
            const next = e ? nodes.find(nn => nn.id === e.target) : null;
            if (next) setTimeout(() => stepTest(next), 220);
            else setTestLog(prev => prev.concat({ kind:'system', text:'Port "purchased" not connected — stop.' }));
          } else {
            setTestLog(prev => prev.concat({ kind:'system', text:`Simulated abandon → port "abandoned"` }));
            const e = edges.find(x => x.source === node.id && x.sourceHandle === 'abandoned');
            const next = e ? nodes.find(nn => nn.id === e.target) : null;
            if (next) setTimeout(() => stepTest(next), 220);
            else setTestLog(prev => prev.concat({ kind:'system', text:'Port "abandoned" not connected — stop.' }));
          }
        }
      }, [edges, nodes]);

      const onAddOption = () => {
        if (!selectedNode) return;
        const opts = (selectedNode.data.options || []).slice();
        if (selectedNode.type === 'buttons' && opts.length >= 3) return toast('Max 3 quick reply buttons');
        if (selectedNode.type === 'ask'     && opts.length >= 3) return toast('Max 3 expected answers');
        if (selectedNode.type === 'poll'    && opts.length >= 3) return toast('Max 3 poll options');
        if (opts.length >= 10) return toast('Max 10 items');
        const defaultLabel = selectedNode.type === 'buttons' ? 'Button '
                           : selectedNode.type === 'ask'     ? 'Answer '
                           : selectedNode.type === 'poll'    ? 'Option '
                           : 'Item ';
        // List items are objects (title/description/section); every other
        // node type still stores a bare string.
        if (selectedNode.type === 'list') {
          // Inherit the last item's section so adding to a section in
          // progress doesn't drop the new row into an unnamed group.
          const lastSection = opts.length ? listOpt(opts[opts.length - 1], opts.length - 1).section : '';
          opts.push({ title: defaultLabel + (opts.length + 1), description: '', section: lastSection });
        } else {
          opts.push(defaultLabel + (opts.length + 1));
        }
        updateNodeData(selectedNode.id, 'options', opts);
      };
      const onRemoveOption = (i) => {
        if (!selectedNode) return;
        const opts = (selectedNode.data.options || []).slice();
        opts.splice(i, 1);
        updateNodeData(selectedNode.id, 'options', opts);
        setEdges(curr => curr.filter(e => !(e.source === selectedNode.id && e.sourceHandle === 'p' + i))
                           .map(e => {
                             if (e.source === selectedNode.id && e.sourceHandle && e.sourceHandle.startsWith('p')) {
                               const idx = parseInt(e.sourceHandle.slice(1), 10);
                               if (idx > i) return { ...e, sourceHandle: 'p' + (idx - 1) };
                             }
                             return e;
                           }));
      };

      const edgePaths = useMemo(() => {
        return edges.map(edge => {
          const src = nodes.find(n => n.id === edge.source);
          const tgt = nodes.find(n => n.id === edge.target);
          if (!src || !tgt) return null;
          const o = portOffsetsRef.current[`out:${edge.source}:${edge.sourceHandle || 'out'}`];
          const i = portOffsetsRef.current[`in:${edge.target}`];
          if (!o || !i) return null;
          const x1 = src.x + o.x, y1 = src.y + o.y;
          const x2 = tgt.x + i.x, y2 = tgt.y + i.y;
          return { id: edge.id, kind: edge.kind, d: bezierPath(x1, y1, x2, y2) };
        }).filter(Boolean);
      }, [nodes, edges, portVer]);

      // Guard rail — block Save/Publish when the graph has broken wiring so a
      // user can't ship a flow that silently dead-ends at runtime. Flags every
      // offending node (red border) + opens the red popup listing each reason.
      // Rules: a non-start node with no incoming edge is unreachable; a
      // non-terminal node (anything but End / Hang up) with no outgoing edge is
      // a dead-end; a node with neither is floating. An empty canvas is allowed.
      // Returns true when the flow is safe to save.
      const runValidation = () => {
        const list = nodes || [];
        if (!list.length) { setInvalidNodes(new Set()); setValidationError(null); return true; }
        const incoming = new Set(), outgoing = new Set();
        (edges || []).forEach(e => { if (e && e.source) outgoing.add(e.source); if (e && e.target) incoming.add(e.target); });
        const startNode = list.find(n => n.isStart) || list.find(n => n.type === 'trigger');
        const bad = new Set(); const items = [];
        // A node is broken ONLY when nothing leads into it (it can never run).
        // We deliberately do NOT require an outgoing edge — a leaf action like
        // "Send message" is a valid end of a flow, so demanding it connect
        // onward was a false positive. The start/trigger node is exempt (it's
        // the entry point; nothing feeds it).
        list.forEach(n => {
          const isStart = (startNode && n.id === startNode.id) || n.type === 'trigger';
          if (isStart) return;
          if (incoming.has(n.id)) return;   // reachable → fine, even if it's a leaf
          const label = (NTYPES[n.type] && NTYPES[n.type].label) || n.type;
          bad.add(n.id);
          items.push(outgoing.has(n.id)
            ? '"' + label + '" has no incoming connection — nothing leads into it.'
            : '"' + label + '" is not connected to anything.');
        });
        // The Trigger node's sender is REQUIRED. Without it the flow has no
        // channel to run on: the keyword rule lands with device_id = NULL and
        // FlowEnrollmentService falls back to "first active device", which on a
        // multi-number workspace silently sends from the wrong number — and on
        // Instagram has no fallback at all.
        // Nodes that the CURRENT channel cannot run. The palette only filters
        // what you can ADD — switching the Trigger's channel leaves everything
        // already on the canvas in place, and the runtime then walks straight
        // past an unsupported node without a word. Catch it here instead of
        // letting the operator ship a flow with silent dead steps.
        const chan = flowTypeOf(list);
        list.forEach(n => {
          const t = NTYPES[n.type];
          if (!t || t.hidden) return;          // unknown/legacy types: leave alone
          if (nodeInMode(t, chan)) return;
          bad.add(n.id);
          items.push('"' + t.label + '" does not work on '
            + (chan === 'instagram' ? 'Instagram' : chan === 'call' ? 'Call' : 'WhatsApp')
            + ' — delete it or switch the channel back on the Trigger node.');
        });

        // ...but ONLY when the workspace has a sender to pick. With nothing
        // connected there is no valid choice, and hard-failing would make every
        // flow in that workspace permanently unsaveable. The Trigger inspector
        // shows an amber "connect one first" note in that case instead.
        const trig = list.find(n => n.type === 'trigger');
        if (trig && !String(trig.data?.deviceId ?? '').trim()) {
          const igFlow = flowTypeOf(list) === 'instagram';
          const cache  = window.__FLOW_PICKER_CACHE__ || {};
          const avail  = (igFlow ? cache.instagram : cache.devices) || [];
          if (avail.length > 0) {
            bad.add(trig.id);
            items.push('The Trigger node has no ' + (igFlow ? 'Instagram account' : 'number')
              + ' selected — pick one on the Trigger step.');
          }
        }

        if (bad.size) {
          setInvalidNodes(bad);
          setValidationError({ items: Array.from(new Set(items)) });
          const firstBad = list.find(n => bad.has(n.id));   // surface a red node
          if (firstBad) setSelectedId(firstBad.id);
          return false;
        }
        setInvalidNodes(new Set());
        setValidationError(null);
        return true;
      };

      // Shared save routine — used by the toolbar's direct "Save changes" and by
      // the name-confirm modal (new flows). Backend routes update-vs-insert off
      // flow_id, so an existing flow saves silently without re-confirming its name.
      const saveFlow = async (name) => {
        if (saveBusy) return;
        setSaveBusy(true);
        try {
          setFlowName(name);
          setStatus('Saving...');
          const data = await postJSON('/flows/api/save', {
            flow_id:   flowId || null,
            flow_name: name,
            category:  category || null,
            flow_type: flowTypeOf(nodes),
            flow_data: { flowNodes: nodes, flowEdges: edges, vars },
          });
          if (!flowId && data?.data?.flow_id) {
            setFlowId(data.data.flow_id);
            window.history.replaceState({}, '', APP_BASE + '/flows/builder/' + data.data.flow_id);
          }
          setStatus(status === 'Published' ? 'Published' : 'Draft');
          toast('Flow saved · "' + name + '"');
          setSaveOpen(false);
        } catch (err) {
          setStatus('Draft · unsaved');
          toast('Save failed: ' + (err?.message || 'unknown'));
        } finally {
          setSaveBusy(false);
        }
      };

      return html`
        <div className="h-screen w-screen flex flex-col">
          <${Toolbar}
            flowId=${flowId}
            flowName=${flowName} setFlowName=${v => { setFlowName(v); setStatus('Draft · unsaved'); }}
            status=${status}
            onUndo=${undo} onRedo=${redo} canUndo=${histIdx > 0} canRedo=${histIdx < history.length - 1}
            onSave=${() => { if (runValidation()) { (flowId && flowName.trim()) ? saveFlow(flowName.trim()) : setSaveOpen(true); } }}
            onPublish=${async () => {
                if (!runValidation()) return;
                try {
                    if (!flowId) {
                        const saved = await postJSON('/flows/api/save', {
                            flow_id:   null,
                            flow_name: flowName,
                            category:  category || null,
                            flow_type: flowTypeOf(nodes),
                            flow_data: { flowNodes: nodes, flowEdges: edges, vars },
                        });
                        if (saved?.data?.flow_id) {
                            setFlowId(saved.data.flow_id);
                            window.history.replaceState({}, '', APP_BASE + '/flows/builder/' + saved.data.flow_id);
                        }
                    }
                    const targetId = flowId || (await postJSON('/flows/api/save', {
                        flow_id: null, flow_name: flowName,
                        flow_type: flowTypeOf(nodes),
                        flow_data: { flowNodes: nodes, flowEdges: edges, vars },
                    })).data.flow_id;
                    await postJSON('/flows/api/publish', { flow_id: targetId });
                    setStatus('Published');
                    toast('Published');
                } catch (err) {
                    toast('Publish failed: ' + err.message);
                }
            }}
            onTest=${() => { setTestOpen(true); startTest(); }}
            onVars=${() => setVarsOpen(true)}
            onAIGen=${() => setAiOpen(true)}
          />

          <div className="flow-canvas-row flex-1 flex min-h-0 relative">
            <${Palette} open=${sidebarOpen} query=${paletteQuery} onSearch=${setPaletteQuery} onAddCenter=${addNodeAtCenter} mode=${flowTypeOf(nodes)} />
            ${sidebarOpen ? html`<div className="flow-palette-backdrop" onClick=${() => setSidebarOpen(false)}></div>` : null}

            <div ref=${wrapperRef}
              onMouseDown=${onCanvasMouseDown}
              onClick=${onCanvasClick}
              onDrop=${onDropNode}
              onDragOver=${e => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; }}
              className=${'canvas-empty canvas-dots flex-1 relative overflow-hidden ' + (panRef.current ? 'dragging' : '')}
              style=${{ cursor: pendingFrom ? 'crosshair' : 'default' }}
            >
              <div className="absolute top-0 left-0 origin-top-left" style=${{ transform:`translate(${pan.x}px, ${pan.y}px) scale(${zoom})`, transformOrigin:'0 0', willChange:'transform' }}>
                <svg width=${4000} height=${3000} style=${{ position:'absolute', left:0, top:0, pointerEvents:'none', overflow:'visible' }}>
                  <defs>
                    <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
                      <path d="M0 0 L10 5 L0 10 z" fill="#3A5A55" />
                    </marker>
                    <marker id="arrow-no" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
                      <path d="M0 0 L10 5 L0 10 z" fill="#E87A5D" />
                    </marker>
                  </defs>
                  <g style=${{ pointerEvents:'auto' }}>
                    ${edgePaths.map(e => html`
                      <g key=${e.id}>
                        <path d=${e.d} className="edge-hit"
                          onMouseEnter=${() => setHoveredEdge(e.id)}
                          onMouseLeave=${() => setHoveredEdge(null)}
                          onClick=${(ev) => {
                            ev.stopPropagation();
                            const r = wrapperRef.current.getBoundingClientRect();
                            const wx = (ev.clientX - r.left - pan.x) / zoom;
                            const wy = (ev.clientY - r.top  - pan.y) / zoom;
                            setEdgePopup({ id: e.id, x: wx, y: wy });
                          }}
                        />
                        <path d=${e.d}
                          className=${'edge-path ' + (e.kind === 'no' ? 'no ' : '') + (hoveredEdge === e.id ? 'hover' : '')}
                          style=${{ pointerEvents:'none' }}
                        />
                      </g>`)}
                    ${tempEdge && pendingFrom ? (() => {
                      const src = nodes.find(n => n.id === pendingFrom.nodeId);
                      if (!src) return null;
                      const o = portOffsetsRef.current[`out:${pendingFrom.nodeId}:${pendingFrom.handleId}`];
                      if (!o) return null;
                      return html`<path className="edge-path preview" d=${bezierPath(src.x+o.x, src.y+o.y, tempEdge.x, tempEdge.y)} />`;
                    })() : null}
                  </g>
                </svg>

                ${nodes.map(node => html`
                  <${NodeCard}
                    key=${node.id}
                    node=${node}
                    selected=${selectedId === node.id}
                    invalid=${invalidNodes.has(node.id)}
                    dragging=${draggingId === node.id}
                    onMouseDown=${onNodeMouseDown}
                    onClick=${(id) => setSelectedId(id)}
                    onPortMouseDown=${onPortMouseDown}
                    onPortHover=${setHoveredPort}
                    hoveredPort=${hoveredPort}
                    onDataChange=${updateNodeData}
                    tplVer=${tplVer}
                  />
                `)}

                ${edgePopup ? html`
                  <div className="edge-popup" style=${{ left: edgePopup.x, top: edgePopup.y - 4 }}
                    onMouseDown=${(e) => e.stopPropagation()}
                    onClick=${(e) => { e.stopPropagation(); deleteEdge(edgePopup.id); setEdgePopup(null); }}
                  >
                    <svg viewBox="0 0 16 16" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9"/></svg>
                    <span>Delete</span>
                  </div>
                ` : null}
              </div>

              ${selectedNode ? html`
                <${NodeActionBar}
                  node=${selectedNode} pan=${pan} zoom=${zoom}
                  onEdit=${() => setInsOpen(true)}
                  onDelete=${() => deleteNodeById(selectedNode.id)}
                  onPin=${() => pinNodeAsStart(selectedNode.id)}
                />
              ` : null}

              <button onClick=${() => setSidebarOpen(!sidebarOpen)}
                className="absolute top-1/2 -translate-y-1/2 left-0 z-20 w-5 h-12 bg-paper-0 border border-l-0 border-paper-200 rounded-r-md grid place-items-center hover:bg-paper-50"
                title=${sidebarOpen ? 'Hide sidebar' : 'Show sidebar'}>
                <${Icon} d=${sidebarOpen ? 'M9 4l-3 4 3 4' : 'M7 4l3 4-3 4'} className="w-3 h-3 text-ink-500" />
              </button>

              <${Minimap} nodes=${nodes} edges=${edges} pan=${pan} zoom=${zoom} setPan=${setPan} wrapperRef=${wrapperRef} />

              <div className="absolute bottom-4 right-4 flex items-center gap-2 z-20">
                <div className="bg-paper-0/95 border border-paper-200 rounded-full shadow-card flex items-center overflow-hidden">
                  <button onClick=${undo} disabled=${!(histIdx > 0)} className="w-9 h-9 grid place-items-center hover:bg-paper-50 disabled:opacity-40 disabled:cursor-not-allowed" title="Undo (Ctrl+Z)"><${Icon} d="M4 8h6a3 3 0 0 1 0 6M4 8l3-3M4 8l3 3" className="w-3.5 h-3.5" /></button>
                  <div className="w-px h-5 bg-paper-200"></div>
                  <button onClick=${redo} disabled=${!(histIdx < history.length - 1)} className="w-9 h-9 grid place-items-center hover:bg-paper-50 disabled:opacity-40 disabled:cursor-not-allowed" title="Redo (Ctrl+Shift+Z)"><${Icon} d="M12 8H6a3 3 0 0 0 0 6M12 8L9 5M12 8l-3 3" className="w-3.5 h-3.5" /></button>
                </div>
                <div className="bg-paper-0/95 border border-paper-200 rounded-full shadow-card flex items-center overflow-hidden">
                  <button onClick=${() => setZoom(z => Math.min(1.8, z + 0.1))} className="w-9 h-9 grid place-items-center hover:bg-paper-50" title="Zoom in"><${Icon} d="M8 4v8M4 8h8" className="w-3.5 h-3.5" /></button>
                  <div className="px-2 text-[10.5px] font-mono text-ink-600 min-w-[44px] text-center border-l border-r border-paper-200 h-9 grid place-items-center">${Math.round(zoom*100)}%</div>
                  <button onClick=${() => setZoom(z => Math.max(0.4, z - 0.1))} className="w-9 h-9 grid place-items-center hover:bg-paper-50" title="Zoom out"><${Icon} d="M4 8h8" className="w-3.5 h-3.5" /></button>
                  <button onClick=${() => { setZoom(1); setPan({ x: 40, y: 40 }); }} className="w-9 h-9 grid place-items-center hover:bg-paper-50 border-l border-paper-200" title="Reset"><${Icon} d="M3 8a5 5 0 1 0 1.5-3.5L3 6M3 3v3h3" className="w-3.5 h-3.5" /></button>
                </div>
              </div>
            </div>

            <${Inspector}
              node=${selectedNode}
              open=${insOpen}
              tplVer=${tplVer}
              onClose=${() => setInsOpen(false)}
              onChange=${(k, v) => selectedNode && updateNodeData(selectedNode.id, k, v)}
              onDelete=${() => selectedNode && deleteNodeById(selectedNode.id)}
              onAddOption=${onAddOption}
              onRemoveOption=${onRemoveOption}
              onCreateAgent=${(cb) => { setNewAgentCb(() => cb); setNewAgentOpen(true); }}
              onPickProducts=${(ctx) => { setProductPickerCtx(ctx); setProductPickerOpen(true); }}
              onRefresh=${() => setTplVer(v => v + 1)}
            />
          </div>

          <${SaveConfirmModal}
            open=${saveOpen}
            busy=${saveBusy}
            initialName=${flowName}
            onClose=${() => { if (!saveBusy) setSaveOpen(false); }}
            onSave=${saveFlow}
          />

          ${validationError ? html`
            <div className="fixed inset-x-0 top-4 z-[90] flex justify-center px-4 pointer-events-none">
              <div className="pointer-events-auto max-w-md w-full bg-paper-0 border-2 border-accent-coral rounded-2xl shadow-soft overflow-hidden">
                <div className="flex items-start gap-3 px-4 py-3 bg-accent-coral/10">
                  <span className="w-8 h-8 rounded-full bg-accent-coral/15 text-accent-coral grid place-items-center shrink-0 mt-0.5">
                    <svg viewBox="0 0 16 16" className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5M8 11h.01"/></svg>
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="font-semibold text-[13.5px] text-ink-900">Can't save — ${validationError.items.length} ${validationError.items.length === 1 ? 'node needs' : 'nodes need'} fixing</div>
                    <ul className="mt-1 space-y-0.5 text-[12px] text-ink-600 list-disc pl-4">
                      ${validationError.items.slice(0, 6).map((m, i) => html`<li key=${i}>${m}</li>`)}
                    </ul>
                    ${validationError.items.length > 6 ? html`<div className="text-[11px] text-ink-500 mt-1">…and ${validationError.items.length - 6} more.</div>` : null}
                    <div className="text-[11px] text-ink-500 mt-1.5">The affected nodes are outlined in red. Connect them, then save again.</div>
                  </div>
                  <button onClick=${() => setValidationError(null)} className="shrink-0 w-7 h-7 rounded-full hover:bg-accent-coral/10 grid place-items-center text-ink-500" title="Dismiss">
                    <svg viewBox="0 0 16 16" className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                  </button>
                </div>
              </div>
            </div>
          ` : null}

          <${AIGenModal} open=${aiOpen} onClose=${() => setAiOpen(false)} initialPrompt=${aiInitialPrompt} flowType=${flowTypeOf(nodes)}
            onGenerate=${(flow, modelLabel) => {
              // Backend (FlowsController::apiAiGenerate) already validated
              // the shape — { flowNodes:[…], flowEdges:[…] } — and capped
              // the size. We still defensively backfill missing ids /
              // sourceHandles so a slightly off model output doesn't
              // crash the React renderer.
              const ns = (flow.flowNodes || []).map(n => ({
                id:   String(n.id || nid()),
                type: String(n.type || 'message'),
                x:    Number.isFinite(n.x) ? n.x : 100,
                y:    Number.isFinite(n.y) ? n.y : 200,
                data: { ...defaultData(String(n.type || 'message')), ...(n.data || {}) },
                isStart: !!n.isStart,
              }));
              // Force exactly one isStart=true — first trigger, or first node.
              const triggerIdx = ns.findIndex(n => n.type === 'trigger');
              const startIdx = triggerIdx >= 0 ? triggerIdx : 0;
              ns.forEach((n, i) => { n.isStart = (i === startIdx); });

              const validIds = new Set(ns.map(n => n.id));
              const es = (flow.flowEdges || [])
                .filter(e => validIds.has(e.source) && validIds.has(e.target))
                .map(e => ({
                  id:           String(e.id || eid()),
                  source:       String(e.source),
                  sourceHandle: String(e.sourceHandle || 'out'),
                  target:       String(e.target),
                }));
              setNodes(ns);
              setEdges(es);
              pushHistory(ns, es);
              setStatus('Draft · unsaved (AI-generated)');
              toast('Flow generated with ' + modelLabel);
            }}
          />
          <${VarsModal} open=${varsOpen} vars=${vars}
            onChange=${(i,k,v) => setVars(curr => curr.map((x,j) => j === i ? { ...x, [k]: v } : x))}
            onAdd=${() => setVars(curr => curr.concat({ name:'new_var', desc:'', default:'' }))}
            onRemove=${i => setVars(curr => curr.filter((_, j) => j !== i))}
            onClose=${() => setVarsOpen(false)}
          />

          <${NewAgentModal}
            open=${newAgentOpen}
            onClose=${() => { setNewAgentOpen(false); setNewAgentCb(null); }}
            onCreated=${(agent) => { newAgentCb?.(agent); setTplVer(v => v + 1); }}
          />

          <${ProductPickerModal}
            open=${productPickerOpen}
            ctx=${productPickerCtx}
            onClose=${() => { setProductPickerOpen(false); setProductPickerCtx(null); }}
          />

          <${TestPanel} open=${testOpen} onClose=${() => setTestOpen(false)} onRestart=${startTest} log=${testLog} awaiting=${testAwaiting} onSend=${sendTest} />

          ${toastMsg ? html`
            <div className="fixed top-20 left-1/2 -translate-x-1/2 bg-ink-900 text-paper-0 px-4 py-2 rounded-full text-[12px] font-medium z-[60] shadow-soft">${toastMsg}</div>
          ` : null}
        </div>
      `;
    }

    const root = createRoot(document.getElementById('root'));
    root.render(html`<${FlowApp} />`);
}
