# Connect your WhatsApp number (Cloud API)

ChatDesk talks to WhatsApp through Meta's **official WhatsApp Cloud API**, using your own Meta app.
You need four things, and this guide shows where each one comes from:

| Value | Where it goes |
| --- | --- |
| **App Secret** | `META_APP_SECRET` in your `.env` |
| **Phone Number ID** | ChatDesk → Settings → WhatsApp → Connect |
| **WhatsApp Business Account ID (WABA ID)** | ChatDesk → Settings → WhatsApp → Connect |
| **Permanent access token** (System User) | ChatDesk → Settings → WhatsApp → Connect |

Then you copy the **Webhook URL** and **Verify token** that ChatDesk shows back into Meta.

> Your ChatDesk site must be reachable over **HTTPS** with a valid certificate. Meta will not
> deliver webhooks to `http://` or to `localhost`. For local testing use a tunnel such as
> Cloudflare Tunnel or ngrok.

---

## 1. Create a Meta Business portfolio

1. Go to <https://business.facebook.com> and create (or pick) your business portfolio.
2. Optional but recommended: **Business settings → Security center → Start verification**.
   Unverified businesses can still message, with lower limits.

## 2. Create the Meta app

1. Open <https://developers.facebook.com/apps> → **Create app**.
2. Use case: **Other** → app type **Business**.
3. Name it (for example "ChatDesk – My Company") and link your business portfolio.
4. On the app dashboard, find **WhatsApp** and click **Set up**.
   Meta creates a test WhatsApp Business Account and a test phone number for you.

## 3. Copy the App Secret

1. In the app: **App settings → Basic**.
2. Next to **App secret**, click **Show** and copy it.
3. Put it in your ChatDesk `.env`:

   ```env
   META_APP_SECRET=paste-the-app-secret-here
   ```

4. Clear the config cache: `php artisan config:clear` (or re-run `php artisan optimize`).

ChatDesk rejects any webhook that is not signed with this secret, so incoming messages will not
appear until it is set.

## 4. Add your real phone number

1. In the app: **WhatsApp → API Setup**.
2. Under **Step 5: Add a phone number**, click **Add phone number** and follow the steps
   (display name, category, verification by SMS or call).
   - The number must not be registered in the WhatsApp or WhatsApp Business **app**. If it is,
     delete that account in the app first.
3. Back on **API Setup**, pick your number in the **From** dropdown. You now see:
   - **Phone number ID** – a long number, for example `109876543210987`
   - **WhatsApp Business Account ID** – also a long number, for example `123456789012345`

   Copy both. These are IDs, not your phone number.

## 5. Create a permanent access token

The token on the API Setup page expires after 24 hours. Create a permanent one with a System User:

1. Go to **Business settings** (<https://business.facebook.com/settings>) → **Users → System users**.
2. Click **Add**, give it a name (for example `chatdesk`) and role **Admin**.
3. Click **Assign assets**:
   - **Apps** → your app → **Full control** (Manage app).
   - **WhatsApp accounts** → your WABA → **Full control**.
4. Click **Generate new token**:
   - App: your app.
   - Token expiration: **Never**.
   - Permissions: `whatsapp_business_messaging` and `whatsapp_business_management`
     (add `business_management` if you want ChatDesk to read business details).
5. Copy the token now. Meta shows it only once. Treat it like a password.

## 6. Add the number in ChatDesk

1. Sign in to ChatDesk → **Settings → WhatsApp account** → **Connect WhatsApp**.
2. Paste the **Phone Number ID**, **WABA ID** and the **permanent token**. Save.
3. ChatDesk shows this connection's **Webhook URL** and **Verify token**. Keep the page open.

## 7. Set up the webhook in Meta

1. In the app: **WhatsApp → Configuration** → **Webhook** → **Edit**.
2. **Callback URL**: the Webhook URL from ChatDesk, for example
   `https://chat.example.com/webhooks/whatsapp/your-connection-slug`
3. **Verify token**: the Verify token from ChatDesk.
4. Click **Verify and save**. Meta calls your site once; ChatDesk answers if the token matches.
5. Under **Webhook fields**, click **Manage** and **Subscribe** to at least:
   - `messages` (incoming messages and delivery/read statuses)
   - `message_template_status_update` (template approvals)
   - optionally `message_template_quality_update`, `phone_number_quality_update`, `account_update`
6. In ChatDesk, on the WhatsApp account page, click **Subscribe webhook** (or run it once from
   **Connection health**). This subscribes your app to the WABA so Meta actually sends events.

## 8. Test it

1. Send a WhatsApp message from your personal phone to the business number.
2. It should appear in **Inbox** within a few seconds. Reply from ChatDesk.
3. Go to **Templates → Sync** to pull your approved templates, or create a new one and submit it.

## 9. Go live

- In the app dashboard switch the app mode from **Development** to **Live**
  (needs a privacy policy URL in **App settings → Basic**).
- Add a payment method in **WhatsApp Manager → Payment settings** so business-initiated
  (template) conversations can be billed by Meta.
- Messaging limits grow automatically as you send good-quality messages.

## Troubleshooting

| Problem | Fix |
| --- | --- |
| "Verify and save" fails | The Callback URL must be HTTPS and public. Check the verify token is copied exactly. Look at `storage/logs/whatsapp-*.log`. |
| Messages never arrive | `META_APP_SECRET` missing or wrong (webhook returns 401). Webhook field `messages` not subscribed. App not subscribed to the WABA (step 7.6). |
| `(#131030) Recipient phone number not in allowed list` | Your app is in Development and you are using the test number. Add the recipient under **API Setup → To**, or use your real number. |
| `(#190) Access token has expired` | You used the 24-hour token. Create a System User token (step 5) and update it in ChatDesk. |
| `(#100) Invalid parameter` on send | Wrong Phone Number ID (you pasted the phone number instead of its ID). |
| Template messages fail with `131047` | The 24-hour customer service window is closed. Use an approved template to start the conversation. |

Need help setting this up? Yoorie offers paid setup: **hello@yoorie.com** · <https://yoorie.com>

*WhatsApp and Meta are trademarks of Meta Platforms, Inc. ChatDesk is not affiliated with or endorsed by Meta.*
