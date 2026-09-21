<?php
/** Exact ordered IDs covering every PR #324 selector tag and representative semantics. */
return array (
    0 => array (
        'name' => 'no selectors lists every PR #324 target',
        'args' => array (),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'category-destructive-policy',
            2 => 'dast-policy-self-test',
            3 => 'endpoint-inventory-drift',
            4 => 'endpoint-inventory-proof',
            5 => 'facebook-paging-host',
            6 => 'get-route-inventory',
            7 => 'input-validation-inventory',
            8 => 'meta-write-ownership',
            9 => 'mutating-route-policy-inventory',
            10 => 'no-shipped-oauth-secrets',
            11 => 'public-surface-inventory',
            12 => 'public-template-visibility',
            13 => 'raw-sql-prefix',
            14 => 'remote-auth-hmac',
            15 => 'remote-auth-return-host',
            16 => 'remote-auth-secret-storage',
            17 => 'security-registry-self-test',
            18 => 'source-contract-fixture-harness',
            19 => 'twitter-card-dom-sinks',
            20 => 'raw-sql-prefix-self-test',
            21 => 'input-validation-authorization-behavioral',
            22 => 'permission-denial-behavioral',
            23 => 'public-handlers-behavioral',
            24 => 'security-lab-config-doctor',
            25 => 'security-lab-doctor-self-test',
            26 => 'public-input-fuzz-policy-self-test',
            27 => 'release-secrets-self-test',
            28 => 'semgrep-fixture-self-test',
            29 => 'public-input-robustness',
            30 => 'release-credential-leak-audit',
            31 => 'semgrep-security-audit',
            32 => 'semgrep-quality-audit',
            33 => 'mutation-harness-self-test',
            34 => 'mutation-audit',
            35 => 'zap-passive-baseline',
            36 => 'nuclei-safe-http-baseline',
        ),
    ),
    1 => array (
        'name' => 'OR within module dimension',
        'args' => array (
            0 => '--module',
            1 => 'database',
            2 => '--module',
            3 => 'mutation',
        ),
        'ids' => array (
            0 => 'raw-sql-prefix',
            1 => 'raw-sql-prefix-self-test',
            2 => 'mutation-harness-self-test',
            3 => 'mutation-audit',
        ),
    ),
    2 => array (
        'name' => 'comma syntax is same OR operation',
        'args' => array (
            0 => '--module=database,mutation',
        ),
        'ids' => array (
            0 => 'raw-sql-prefix',
            1 => 'raw-sql-prefix-self-test',
            2 => 'mutation-harness-self-test',
            3 => 'mutation-audit',
        ),
    ),
    3 => array (
        'name' => 'AND across module and category',
        'args' => array (
            0 => '--module',
            1 => 'public-handlers',
            2 => '--category',
            3 => 'authorization',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'public-surface-inventory',
            2 => 'public-template-visibility',
            3 => 'public-handlers-behavioral',
        ),
    ),
    4 => array (
        'name' => 'core plugin owns every PR #324 target',
        'args' => array (
            0 => '--plugin',
            1 => 'core',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'category-destructive-policy',
            2 => 'dast-policy-self-test',
            3 => 'endpoint-inventory-drift',
            4 => 'endpoint-inventory-proof',
            5 => 'facebook-paging-host',
            6 => 'get-route-inventory',
            7 => 'input-validation-inventory',
            8 => 'meta-write-ownership',
            9 => 'mutating-route-policy-inventory',
            10 => 'no-shipped-oauth-secrets',
            11 => 'public-surface-inventory',
            12 => 'public-template-visibility',
            13 => 'raw-sql-prefix',
            14 => 'remote-auth-hmac',
            15 => 'remote-auth-return-host',
            16 => 'remote-auth-secret-storage',
            17 => 'security-registry-self-test',
            18 => 'source-contract-fixture-harness',
            19 => 'twitter-card-dom-sinks',
            20 => 'raw-sql-prefix-self-test',
            21 => 'input-validation-authorization-behavioral',
            22 => 'permission-denial-behavioral',
            23 => 'public-handlers-behavioral',
            24 => 'security-lab-config-doctor',
            25 => 'security-lab-doctor-self-test',
            26 => 'public-input-fuzz-policy-self-test',
            27 => 'release-secrets-self-test',
            28 => 'semgrep-fixture-self-test',
            29 => 'public-input-robustness',
            30 => 'release-credential-leak-audit',
            31 => 'semgrep-security-audit',
            32 => 'semgrep-quality-audit',
            33 => 'mutation-harness-self-test',
            34 => 'mutation-audit',
            35 => 'zap-passive-baseline',
            36 => 'nuclei-safe-http-baseline',
        ),
    ),
    5 => array (
        'name' => 'exact module tag: ajax',
        'args' => array (
            0 => '--module',
            1 => 'ajax',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'endpoint-inventory-drift',
            2 => 'public-surface-inventory',
            3 => 'public-handlers-behavioral',
            4 => 'public-input-fuzz-policy-self-test',
            5 => 'public-input-robustness',
        ),
    ),
    6 => array (
        'name' => 'exact module tag: chat-widgets',
        'args' => array (
            0 => '--module',
            1 => 'chat-widgets',
        ),
        'ids' => array (
            0 => 'meta-write-ownership',
        ),
    ),
    7 => array (
        'name' => 'exact module tag: dast',
        'args' => array (
            0 => '--module',
            1 => 'dast',
        ),
        'ids' => array (
            0 => 'dast-policy-self-test',
            1 => 'zap-passive-baseline',
            2 => 'nuclei-safe-http-baseline',
        ),
    ),
    8 => array (
        'name' => 'exact module tag: database',
        'args' => array (
            0 => '--module',
            1 => 'database',
        ),
        'ids' => array (
            0 => 'raw-sql-prefix',
            1 => 'raw-sql-prefix-self-test',
        ),
    ),
    9 => array (
        'name' => 'exact module tag: endpoint-inventory',
        'args' => array (
            0 => '--module',
            1 => 'endpoint-inventory',
        ),
        'ids' => array (
            0 => 'endpoint-inventory-drift',
            1 => 'endpoint-inventory-proof',
        ),
    ),
    10 => array (
        'name' => 'exact module tag: facebook-feeds',
        'args' => array (
            0 => '--module',
            1 => 'facebook-feeds',
        ),
        'ids' => array (
            0 => 'facebook-paging-host',
        ),
    ),
    11 => array (
        'name' => 'exact module tag: image-optimization',
        'args' => array (
            0 => '--module',
            1 => 'image-optimization',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
        ),
    ),
    12 => array (
        'name' => 'exact module tag: input-validation',
        'args' => array (
            0 => '--module',
            1 => 'input-validation',
        ),
        'ids' => array (
            0 => 'input-validation-inventory',
            1 => 'input-validation-authorization-behavioral',
        ),
    ),
    13 => array (
        'name' => 'exact module tag: javascript',
        'args' => array (
            0 => '--module',
            1 => 'javascript',
        ),
        'ids' => array (
            0 => 'semgrep-security-audit',
            1 => 'semgrep-quality-audit',
        ),
    ),
    14 => array (
        'name' => 'exact module tag: mutation',
        'args' => array (
            0 => '--module',
            1 => 'mutation',
        ),
        'ids' => array (
            0 => 'mutation-harness-self-test',
            1 => 'mutation-audit',
        ),
    ),
    15 => array (
        'name' => 'exact module tag: oauth',
        'args' => array (
            0 => '--module',
            1 => 'oauth',
        ),
        'ids' => array (
            0 => 'no-shipped-oauth-secrets',
            1 => 'remote-auth-hmac',
            2 => 'remote-auth-return-host',
            3 => 'remote-auth-secret-storage',
        ),
    ),
    16 => array (
        'name' => 'exact module tag: php',
        'args' => array (
            0 => '--module',
            1 => 'php',
        ),
        'ids' => array (
            0 => 'semgrep-security-audit',
            1 => 'semgrep-quality-audit',
        ),
    ),
    17 => array (
        'name' => 'exact module tag: public-handlers',
        'args' => array (
            0 => '--module',
            1 => 'public-handlers',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'public-surface-inventory',
            2 => 'public-template-visibility',
            3 => 'public-handlers-behavioral',
            4 => 'public-input-fuzz-policy-self-test',
            5 => 'public-input-robustness',
        ),
    ),
    18 => array (
        'name' => 'exact module tag: public-listeners',
        'args' => array (
            0 => '--module',
            1 => 'public-listeners',
        ),
        'ids' => array (
            0 => 'endpoint-inventory-drift',
        ),
    ),
    19 => array (
        'name' => 'exact module tag: release-artifacts',
        'args' => array (
            0 => '--module',
            1 => 'release-artifacts',
        ),
        'ids' => array (
            0 => 'release-secrets-self-test',
            1 => 'release-credential-leak-audit',
        ),
    ),
    20 => array (
        'name' => 'exact module tag: remote-auth',
        'args' => array (
            0 => '--module',
            1 => 'remote-auth',
        ),
        'ids' => array (
            0 => 'no-shipped-oauth-secrets',
            1 => 'remote-auth-hmac',
            2 => 'remote-auth-return-host',
            3 => 'remote-auth-secret-storage',
        ),
    ),
    21 => array (
        'name' => 'exact module tag: rest-api',
        'args' => array (
            0 => '--module',
            1 => 'rest-api',
        ),
        'ids' => array (
            0 => 'category-destructive-policy',
            1 => 'endpoint-inventory-drift',
            2 => 'get-route-inventory',
            3 => 'input-validation-inventory',
            4 => 'meta-write-ownership',
            5 => 'mutating-route-policy-inventory',
            6 => 'public-surface-inventory',
            7 => 'input-validation-authorization-behavioral',
            8 => 'permission-denial-behavioral',
            9 => 'public-handlers-behavioral',
        ),
    ),
    22 => array (
        'name' => 'exact module tag: review-categories',
        'args' => array (
            0 => '--module',
            1 => 'review-categories',
        ),
        'ids' => array (
            0 => 'category-destructive-policy',
        ),
    ),
    23 => array (
        'name' => 'exact module tag: review-templates',
        'args' => array (
            0 => '--module',
            1 => 'review-templates',
        ),
        'ids' => array (
            0 => 'meta-write-ownership',
        ),
    ),
    24 => array (
        'name' => 'exact module tag: robustness',
        'args' => array (
            0 => '--module',
            1 => 'robustness',
        ),
        'ids' => array (
            0 => 'public-input-fuzz-policy-self-test',
            1 => 'public-input-robustness',
        ),
    ),
    25 => array (
        'name' => 'exact module tag: sast',
        'args' => array (
            0 => '--module',
            1 => 'sast',
        ),
        'ids' => array (
            0 => 'semgrep-fixture-self-test',
            1 => 'semgrep-security-audit',
            2 => 'semgrep-quality-audit',
        ),
    ),
    26 => array (
        'name' => 'exact module tag: secrets-management',
        'args' => array (
            0 => '--module',
            1 => 'secrets-management',
        ),
        'ids' => array (
            0 => 'release-secrets-self-test',
            1 => 'release-credential-leak-audit',
        ),
    ),
    27 => array (
        'name' => 'exact module tag: security-lab',
        'args' => array (
            0 => '--module',
            1 => 'security-lab',
        ),
        'ids' => array (
            0 => 'security-lab-config-doctor',
            1 => 'security-lab-doctor-self-test',
            2 => 'public-input-robustness',
            3 => 'zap-passive-baseline',
            4 => 'nuclei-safe-http-baseline',
        ),
    ),
    28 => array (
        'name' => 'exact module tag: security-registry',
        'args' => array (
            0 => '--module',
            1 => 'security-registry',
        ),
        'ids' => array (
            0 => 'security-registry-self-test',
        ),
    ),
    29 => array (
        'name' => 'exact module tag: security-tooling',
        'args' => array (
            0 => '--module',
            1 => 'security-tooling',
        ),
        'ids' => array (
            0 => 'dast-policy-self-test',
            1 => 'endpoint-inventory-proof',
            2 => 'source-contract-fixture-harness',
            3 => 'raw-sql-prefix-self-test',
            4 => 'security-lab-config-doctor',
            5 => 'security-lab-doctor-self-test',
            6 => 'public-input-fuzz-policy-self-test',
            7 => 'release-secrets-self-test',
            8 => 'semgrep-fixture-self-test',
            9 => 'mutation-harness-self-test',
            10 => 'mutation-audit',
        ),
    ),
    30 => array (
        'name' => 'exact module tag: shortcodes',
        'args' => array (
            0 => '--module',
            1 => 'shortcodes',
        ),
        'ids' => array (
            0 => 'endpoint-inventory-drift',
            1 => 'public-surface-inventory',
            2 => 'public-template-visibility',
            3 => 'public-handlers-behavioral',
        ),
    ),
    31 => array (
        'name' => 'exact module tag: source-contracts',
        'args' => array (
            0 => '--module',
            1 => 'source-contracts',
        ),
        'ids' => array (
            0 => 'source-contract-fixture-harness',
        ),
    ),
    32 => array (
        'name' => 'exact module tag: static-analysis',
        'args' => array (
            0 => '--module',
            1 => 'static-analysis',
        ),
        'ids' => array (
            0 => 'semgrep-fixture-self-test',
            1 => 'semgrep-security-audit',
            2 => 'semgrep-quality-audit',
        ),
    ),
    33 => array (
        'name' => 'exact module tag: template-redirect',
        'args' => array (
            0 => '--module',
            1 => 'template-redirect',
        ),
        'ids' => array (
            0 => 'public-surface-inventory',
            1 => 'public-handlers-behavioral',
        ),
    ),
    34 => array (
        'name' => 'exact module tag: twitter-cards',
        'args' => array (
            0 => '--module',
            1 => 'twitter-cards',
        ),
        'ids' => array (
            0 => 'twitter-card-dom-sinks',
        ),
    ),
    35 => array (
        'name' => 'exact module tag: youtube',
        'args' => array (
            0 => '--module',
            1 => 'youtube',
        ),
        'ids' => array (
            0 => 'public-template-visibility',
        ),
    ),
    36 => array (
        'name' => 'exact category tag: attack-surface',
        'args' => array (
            0 => '--category',
            1 => 'attack-surface',
        ),
        'ids' => array (
            0 => 'endpoint-inventory-drift',
            1 => 'public-surface-inventory',
        ),
    ),
    37 => array (
        'name' => 'exact category tag: authorization',
        'args' => array (
            0 => '--category',
            1 => 'authorization',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'category-destructive-policy',
            2 => 'meta-write-ownership',
            3 => 'mutating-route-policy-inventory',
            4 => 'public-surface-inventory',
            5 => 'public-template-visibility',
            6 => 'input-validation-authorization-behavioral',
            7 => 'permission-denial-behavioral',
            8 => 'public-handlers-behavioral',
        ),
    ),
    38 => array (
        'name' => 'exact category tag: credential-leak-prevention',
        'args' => array (
            0 => '--category',
            1 => 'credential-leak-prevention',
        ),
        'ids' => array (
            0 => 'release-secrets-self-test',
            1 => 'release-credential-leak-audit',
        ),
    ),
    39 => array (
        'name' => 'exact category tag: csrf',
        'args' => array (
            0 => '--category',
            1 => 'csrf',
        ),
        'ids' => array (
            0 => 'ajax-guard-order',
            1 => 'public-handlers-behavioral',
        ),
    ),
    40 => array (
        'name' => 'exact category tag: dast',
        'args' => array (
            0 => '--category',
            1 => 'dast',
        ),
        'ids' => array (
            0 => 'dast-policy-self-test',
            1 => 'zap-passive-baseline',
            2 => 'nuclei-safe-http-baseline',
        ),
    ),
    41 => array (
        'name' => 'exact category tag: destructive-action',
        'args' => array (
            0 => '--category',
            1 => 'destructive-action',
        ),
        'ids' => array (
            0 => 'category-destructive-policy',
        ),
    ),
    42 => array (
        'name' => 'exact category tag: encryption-at-rest',
        'args' => array (
            0 => '--category',
            1 => 'encryption-at-rest',
        ),
        'ids' => array (
            0 => 'remote-auth-secret-storage',
        ),
    ),
    43 => array (
        'name' => 'exact category tag: environment-safety',
        'args' => array (
            0 => '--category',
            1 => 'environment-safety',
        ),
        'ids' => array (
            0 => 'security-lab-config-doctor',
            1 => 'security-lab-doctor-self-test',
        ),
    ),
    44 => array (
        'name' => 'exact category tag: input-robustness',
        'args' => array (
            0 => '--category',
            1 => 'input-robustness',
        ),
        'ids' => array (
            0 => 'public-input-fuzz-policy-self-test',
            1 => 'public-input-robustness',
        ),
    ),
    45 => array (
        'name' => 'exact category tag: input-validation',
        'args' => array (
            0 => '--category',
            1 => 'input-validation',
        ),
        'ids' => array (
            0 => 'input-validation-authorization-behavioral',
            1 => 'public-handlers-behavioral',
            2 => 'semgrep-security-audit',
        ),
    ),
    46 => array (
        'name' => 'exact category tag: inventory-drift',
        'args' => array (
            0 => '--category',
            1 => 'inventory-drift',
        ),
        'ids' => array (
            0 => 'endpoint-inventory-drift',
            1 => 'endpoint-inventory-proof',
            2 => 'get-route-inventory',
            3 => 'input-validation-inventory',
            4 => 'mutating-route-policy-inventory',
            5 => 'public-surface-inventory',
        ),
    ),
    47 => array (
        'name' => 'exact category tag: mutation-coverage',
        'args' => array (
            0 => '--category',
            1 => 'mutation-coverage',
        ),
        'ids' => array (
            0 => 'mutation-audit',
        ),
    ),
    48 => array (
        'name' => 'exact category tag: object-ownership',
        'args' => array (
            0 => '--category',
            1 => 'object-ownership',
        ),
        'ids' => array (
            0 => 'meta-write-ownership',
        ),
    ),
    49 => array (
        'name' => 'exact category tag: object-visibility',
        'args' => array (
            0 => '--category',
            1 => 'object-visibility',
        ),
        'ids' => array (
            0 => 'public-template-visibility',
        ),
    ),
    50 => array (
        'name' => 'exact category tag: open-redirect',
        'args' => array (
            0 => '--category',
            1 => 'open-redirect',
        ),
        'ids' => array (
            0 => 'remote-auth-return-host',
        ),
    ),
    51 => array (
        'name' => 'exact category tag: option-autoload',
        'args' => array (
            0 => '--category',
            1 => 'option-autoload',
        ),
        'ids' => array (
            0 => 'remote-auth-secret-storage',
        ),
    ),
    52 => array (
        'name' => 'exact category tag: output-escaping',
        'args' => array (
            0 => '--category',
            1 => 'output-escaping',
        ),
        'ids' => array (
            0 => 'twitter-card-dom-sinks',
            1 => 'input-validation-authorization-behavioral',
            2 => 'public-handlers-behavioral',
            3 => 'semgrep-security-audit',
        ),
    ),
    53 => array (
        'name' => 'exact category tag: passive-scan',
        'args' => array (
            0 => '--category',
            1 => 'passive-scan',
        ),
        'ids' => array (
            0 => 'zap-passive-baseline',
            1 => 'nuclei-safe-http-baseline',
        ),
    ),
    54 => array (
        'name' => 'exact category tag: rate-limiting',
        'args' => array (
            0 => '--category',
            1 => 'rate-limiting',
        ),
        'ids' => array (
            0 => 'public-handlers-behavioral',
        ),
    ),
    55 => array (
        'name' => 'exact category tag: request-signing',
        'args' => array (
            0 => '--category',
            1 => 'request-signing',
        ),
        'ids' => array (
            0 => 'remote-auth-hmac',
        ),
    ),
    56 => array (
        'name' => 'exact category tag: sast',
        'args' => array (
            0 => '--category',
            1 => 'sast',
        ),
        'ids' => array (
            0 => 'semgrep-security-audit',
            1 => 'semgrep-quality-audit',
        ),
    ),
    57 => array (
        'name' => 'exact category tag: secrets-management',
        'args' => array (
            0 => '--category',
            1 => 'secrets-management',
        ),
        'ids' => array (
            0 => 'no-shipped-oauth-secrets',
            1 => 'remote-auth-secret-storage',
        ),
    ),
    58 => array (
        'name' => 'exact category tag: security-quality',
        'args' => array (
            0 => '--category',
            1 => 'security-quality',
        ),
        'ids' => array (
            0 => 'semgrep-quality-audit',
        ),
    ),
    59 => array (
        'name' => 'exact category tag: source-contract-proof',
        'args' => array (
            0 => '--category',
            1 => 'source-contract-proof',
        ),
        'ids' => array (
            0 => 'source-contract-fixture-harness',
            1 => 'semgrep-fixture-self-test',
        ),
    ),
    60 => array (
        'name' => 'exact category tag: ssrf',
        'args' => array (
            0 => '--category',
            1 => 'ssrf',
        ),
        'ids' => array (
            0 => 'facebook-paging-host',
        ),
    ),
    61 => array (
        'name' => 'exact category tag: table-prefix-safety',
        'args' => array (
            0 => '--category',
            1 => 'table-prefix-safety',
        ),
        'ids' => array (
            0 => 'raw-sql-prefix',
        ),
    ),
    62 => array (
        'name' => 'exact category tag: tooling-self-test',
        'args' => array (
            0 => '--category',
            1 => 'tooling-self-test',
        ),
        'ids' => array (
            0 => 'dast-policy-self-test',
            1 => 'endpoint-inventory-proof',
            2 => 'security-registry-self-test',
            3 => 'source-contract-fixture-harness',
            4 => 'raw-sql-prefix-self-test',
            5 => 'security-lab-doctor-self-test',
            6 => 'public-input-fuzz-policy-self-test',
            7 => 'release-secrets-self-test',
            8 => 'semgrep-fixture-self-test',
            9 => 'mutation-harness-self-test',
        ),
    ),
    63 => array (
        'name' => 'exact category tag: url-validation',
        'args' => array (
            0 => '--category',
            1 => 'url-validation',
        ),
        'ids' => array (
            0 => 'facebook-paging-host',
            1 => 'remote-auth-return-host',
            2 => 'twitter-card-dom-sinks',
        ),
    ),
    64 => array (
        'name' => 'exact category tag: xss',
        'args' => array (
            0 => '--category',
            1 => 'xss',
        ),
        'ids' => array (
            0 => 'twitter-card-dom-sinks',
        ),
    ),
);
