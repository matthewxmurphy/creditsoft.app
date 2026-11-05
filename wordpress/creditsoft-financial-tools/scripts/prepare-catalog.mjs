// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Built Not Begged https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Adapter 2026.10.1 UNRELEASED.
// Prepare only the PHP-readable descriptor; calculator formulas remain in the adopted model.
import { writeFile } from 'node:fs/promises';
import { tools, sourceLink } from '../assets/catalog.mjs';
await writeFile(new URL('../assets/catalog.json', import.meta.url), JSON.stringify({ tools, sourceLink }, null, 2) + '\n');
