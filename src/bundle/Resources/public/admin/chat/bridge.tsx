import React from 'react';
import type { ReactNode } from 'react';

import { iconPath } from '@masilia-admin-kit/icons';
import { AssetsProvider } from '@masilia-admin-kit/ids';

/** The Design System components need to know where the icon sprite is. */
export function AssetsBridge({ children }: { children: ReactNode }) {
    return <AssetsProvider value={{ getIconPath: iconPath }}>{children}</AssetsProvider>;
}
