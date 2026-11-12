import React from 'react';
import type { ReactNode } from 'react';

import { iconPath } from '../shared/icons';
import { AssetsProvider } from '../shared/ids';

/** The Design System components need to know where the icon sprite is. */
export function AssetsBridge({ children }: { children: ReactNode }) {
    return <AssetsProvider value={{ getIconPath: iconPath }}>{children}</AssetsProvider>;
}
