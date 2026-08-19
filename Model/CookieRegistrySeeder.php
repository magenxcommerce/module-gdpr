<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

use Magenx\Gdpr\Model\ResourceModel\Cookie as CookieResource;
use Magenx\Gdpr\Model\ResourceModel\Cookie\CollectionFactory as CookieCollectionFactory;
use Magenx\Gdpr\Model\ResourceModel\CookieGroup as CookieGroupResource;
use Magenx\Gdpr\Model\ResourceModel\CookieGroup\CollectionFactory as CookieGroupCollectionFactory;

/**
 * Installs / restores the default cookie-group and cookie rows.
 *
 * The cookie list intentionally reflects only what this headless storefront
 * actually sets in the browser (see apps/theme/src/lib/auth-cookie.ts and
 * auth.ts in the magenxcommerce repo) plus the Google Analytics cookies a
 * merchant would see once GTM/GA is configured. It is not a generic
 * "every cookie a Magento site might set" list — the whole point of this
 * module is that the cookie policy page stops guessing.
 *
 * Used both by the install-time data patch and by the
 * `magenx:gdpr:seed-cookies` CLI command, so there is one seed dataset.
 */
class CookieRegistrySeeder
{
    /** @var array<int, array{code: string, label: string, description: string, is_required: int, sort_order: int}> */
    private const GROUPS = [
        [
            'code' => 'necessary',
            'label' => 'Necessary',
            'description' => 'Required for the storefront and your account to work. These are never optional.',
            'is_required' => 1,
            'sort_order' => 10,
        ],
        [
            'code' => 'analytics',
            'label' => 'Analytics',
            'description' => 'Help us understand how visitors use the store so we can improve it. Only set once you accept them.',
            'is_required' => 0,
            'sort_order' => 20,
        ],
        [
            'code' => 'marketing',
            'label' => 'Marketing',
            'description' => 'Used for ad targeting and remarketing. Only set once you accept them.',
            'is_required' => 0,
            'sort_order' => 30,
        ],
        [
            'code' => 'preferences',
            'label' => 'Preferences',
            'description' => 'Remember choices such as region or recently viewed items. Only set once you accept them.',
            'is_required' => 0,
            'sort_order' => 40,
        ],
    ];

    /**
     * @var array<int, array{name: string, group_code: string, source: string, purpose: string,
     *     duration_label: string, is_active: int, sort_order: int}>
     */
    private const COOKIES = [
        [
            'name' => 'magenx_ct',
            'group_code' => 'necessary',
            'source' => 'auth',
            'purpose' => 'Holds your signed-in session token (httpOnly - never readable by page scripts).',
            'duration_label' => '1 hour',
            'is_active' => 1,
            'sort_order' => 10,
        ],
        [
            'name' => 'magenx_auth',
            'group_code' => 'necessary',
            'source' => 'auth',
            'purpose' => 'Lets the storefront UI know you are signed in. Carries no secret value.',
            'duration_label' => '1 hour',
            'is_active' => 1,
            'sort_order' => 20,
        ],
        [
            'name' => 'authjs.session-token',
            'group_code' => 'necessary',
            'source' => 'auth',
            'purpose' => 'Short-lived broker session used only while completing Google/Apple sign-in.',
            'duration_label' => '5 minutes',
            'is_active' => 1,
            'sort_order' => 30,
        ],
        [
            'name' => '__Secure-authjs.session-token',
            'group_code' => 'necessary',
            'source' => 'auth',
            'purpose' => 'Secure-context variant of the sign-in broker session cookie (HTTPS deployments).',
            'duration_label' => '5 minutes',
            'is_active' => 1,
            'sort_order' => 40,
        ],
        [
            'name' => 'authjs.csrf-token',
            'group_code' => 'necessary',
            'source' => 'auth',
            'purpose' => 'Protects the Google/Apple sign-in redirect against cross-site request forgery.',
            'duration_label' => 'Session',
            'is_active' => 1,
            'sort_order' => 50,
        ],
        [
            'name' => '__Host-authjs.csrf-token',
            'group_code' => 'necessary',
            'source' => 'auth',
            'purpose' => 'Secure-context variant of the sign-in CSRF cookie (HTTPS deployments).',
            'duration_label' => 'Session',
            'is_active' => 1,
            'sort_order' => 60,
        ],
        [
            'name' => '_ga',
            'group_code' => 'analytics',
            'source' => 'google',
            'purpose' => 'Google Analytics - distinguishes unique visitors.',
            'duration_label' => '2 years',
            'is_active' => 0,
            'sort_order' => 10,
        ],
        [
            'name' => '_ga_*',
            'group_code' => 'analytics',
            'source' => 'google',
            'purpose' => 'Google Analytics - maintains session state for a specific GA4 property.',
            'duration_label' => '2 years',
            'is_active' => 0,
            'sort_order' => 20,
        ],
        [
            'name' => '_gid',
            'group_code' => 'analytics',
            'source' => 'google',
            'purpose' => 'Google Analytics - distinguishes visitors over a short window.',
            'duration_label' => '24 hours',
            'is_active' => 0,
            'sort_order' => 30,
        ],
    ];

    public function __construct(
        private readonly CookieGroupFactory $groupFactory,
        private readonly CookieGroupResource $groupResource,
        private readonly CookieGroupCollectionFactory $groupCollectionFactory,
        private readonly CookieFactory $cookieFactory,
        private readonly CookieResource $cookieResource,
        private readonly CookieCollectionFactory $cookieCollectionFactory
    ) {
    }

    /**
     * Upserts every default group and cookie by its natural key (code / name),
     * so re-running is always safe: existing customizations to unrelated rows
     * are untouched, and a row an admin deleted comes back with its defaults.
     */
    public function seed(): void
    {
        $groupIdByCode = [];

        foreach (self::GROUPS as $data) {
            $collection = $this->groupCollectionFactory->create();
            $collection->addFieldToFilter('code', $data['code']);
            $group = $collection->getFirstItem();
            if (!$group->getId()) {
                $group = $this->groupFactory->create();
            }
            $group->addData($data);
            $this->groupResource->save($group);
            $groupIdByCode[$data['code']] = (int) $group->getId();
        }

        foreach (self::COOKIES as $data) {
            $groupId = $groupIdByCode[$data['group_code']] ?? null;
            if ($groupId === null) {
                continue;
            }
            unset($data['group_code']);
            $data['group_id'] = $groupId;

            $collection = $this->cookieCollectionFactory->create();
            $collection->addFieldToFilter('name', $data['name']);
            $cookie = $collection->getFirstItem();
            if (!$cookie->getId()) {
                $cookie = $this->cookieFactory->create();
            }
            $cookie->addData($data);
            $this->cookieResource->save($cookie);
        }
    }
}
