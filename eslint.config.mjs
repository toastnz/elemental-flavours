/* Notes:
 * Same intent as the project's root config: catch programming errors, leave
 * formatting alone. This module needs one of its own for two reasons. Its
 * source sits outside the `files` the root config covers, so linting here was
 * matching no config at all - which is why `yarn build` stopped at the first
 * `<` in a JSX file, with the parser left on its defaults. And the module is
 * meant to be installable on its own, where there is no root config to inherit.
 *
 * Globals are listed by hand rather than pulled from the `globals` package: two
 * of them are used, and the package is only here by hoisting from the project
 * above, which wouldn't follow the module elsewhere. */

import react from 'eslint-plugin-react';

export default [
    {
        files: ['client/src/js/**/*.js'],
        plugins: {
            react,
        },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            parserOptions: {
                ecmaFeatures: {
                    jsx: true,
                },
            },
            globals: {
                document: 'readonly',
                window: 'readonly',
                URL: 'readonly',
                // For the <elemental-flavour> custom element, which talks to the DOM directly
                HTMLElement: 'readonly',
                HTMLLinkElement: 'readonly',
                MutationObserver: 'readonly',
                getComputedStyle: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
            },
        },
        rules: {
            // JSX errors
            //
            // Only the two rules that teach no-unused-vars to read JSX: without
            // them every imported component, and React itself, looks unused.
            // The rest of the plugin is style, which this config stays out of.
            'react/jsx-uses-react': 'error',
            'react/jsx-uses-vars': 'error',

            // Variable and scope errors
            'no-unused-vars': ['warn', { varsIgnorePattern: '^_', argsIgnorePattern: '^_' }],
            'no-undef': 'error',
            'no-redeclare': 'error',
            'no-use-before-define': 'error',
            'prefer-const': 'warn',

            // Runtime errors
            'no-unreachable': 'error',
            'no-dupe-keys': 'error',
            'no-dupe-args': 'error',
            'no-duplicate-case': 'error',
            'no-func-assign': 'error',
            'no-import-assign': 'error',
            'no-const-assign': 'error',
            'no-global-assign': 'error',

            // Type and value errors
            'use-isnan': 'error',
            'valid-typeof': 'error',
            'no-obj-calls': 'error',
            'no-sparse-arrays': 'error',
            'no-invalid-regexp': 'error',

            // Logic errors
            'no-constant-condition': 'warn',
            'no-fallthrough': 'warn',
            'no-self-compare': 'warn',
            'no-empty': 'warn',
            'array-callback-return': 'error',

            // Constructor and object creation
            'no-constructor-return': 'error',
            'no-array-constructor': 'warn',
            'no-new-wrappers': 'error',
        },
    },
];
