type PermissionStateLike = 'granted' | 'denied' | 'prompt' | 'missing' | 'unsupported';

type PickerWindow = Window & {
    showDirectoryPicker?: (options?: {
        id?: string;
        mode?: 'read' | 'readwrite';
    }) => Promise<FileSystemDirectoryHandle>;
};

type FileSystemAccessPermissionDescriptor = {
    mode?: 'read' | 'readwrite';
};

type PermissionCapableDirectoryHandle = FileSystemDirectoryHandle & {
    queryPermission?: (descriptor?: FileSystemAccessPermissionDescriptor) => Promise<PermissionState>;
    requestPermission?: (descriptor?: FileSystemAccessPermissionDescriptor) => Promise<PermissionState>;
};

type StoredDirectoryState = {
    supported: boolean;
    name: string | null;
    permission: PermissionStateLike;
    handle: FileSystemDirectoryHandle | null;
};

const DATABASE_NAME = 'creditsoft-local-backup';
const STORE_NAME = 'handles';
const HANDLE_KEY = 'preferred-local-backup-directory';

async function openDatabase(): Promise<IDBDatabase> {
    return await new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, 1);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (! database.objectStoreNames.contains(STORE_NAME)) {
                database.createObjectStore(STORE_NAME);
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('Could not open local backup directory storage.'));
    });
}

async function getStoredHandle(): Promise<FileSystemDirectoryHandle | null> {
    if (! ('indexedDB' in window)) {
        return null;
    }

    const database = await openDatabase();

    return await new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readonly');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.get(HANDLE_KEY);

        request.onsuccess = () => resolve((request.result as FileSystemDirectoryHandle | undefined) ?? null);
        request.onerror = () => reject(request.error ?? new Error('Could not read the preferred backup directory.'));
        transaction.oncomplete = () => database.close();
    });
}

async function setStoredHandle(handle: FileSystemDirectoryHandle): Promise<void> {
    const database = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.put(handle, HANDLE_KEY);

        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error ?? new Error('Could not save the preferred backup directory.'));
        transaction.oncomplete = () => database.close();
    });
}

export async function clearLocalBackupDirectory(): Promise<void> {
    if (! ('indexedDB' in window)) {
        return;
    }

    const database = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.delete(HANDLE_KEY);

        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error ?? new Error('Could not clear the preferred backup directory.'));
        transaction.oncomplete = () => database.close();
    });
}

async function permissionFor(handle: FileSystemDirectoryHandle): Promise<PermissionStateLike> {
    const permissionHandle = handle as PermissionCapableDirectoryHandle;

    if (typeof permissionHandle.queryPermission !== 'function') {
        return 'prompt';
    }

    return await permissionHandle.queryPermission({ mode: 'readwrite' });
}

export function supportsLocalBackupDirectoryPicker(): boolean {
    return typeof (window as PickerWindow).showDirectoryPicker === 'function';
}

export async function getLocalBackupDirectoryState(): Promise<StoredDirectoryState> {
    if (! supportsLocalBackupDirectoryPicker()) {
        return {
            supported: false,
            name: null,
            permission: 'unsupported',
            handle: null,
        };
    }

    const handle = await getStoredHandle();

    if (! handle) {
        return {
            supported: true,
            name: null,
            permission: 'missing',
            handle: null,
        };
    }

    return {
        supported: true,
        name: handle.name,
        permission: await permissionFor(handle),
        handle,
    };
}

export async function chooseLocalBackupDirectory(): Promise<StoredDirectoryState> {
    const picker = (window as PickerWindow).showDirectoryPicker;

    if (typeof picker !== 'function') {
        return {
            supported: false,
            name: null,
            permission: 'unsupported',
            handle: null,
        };
    }

    const handle = await picker({
        id: 'creditsoft-local-backup-directory',
        mode: 'readwrite',
    });

    const permissionHandle = handle as PermissionCapableDirectoryHandle;

    if (typeof permissionHandle.requestPermission === 'function') {
        await permissionHandle.requestPermission({ mode: 'readwrite' });
    }

    await setStoredHandle(handle);

    return {
        supported: true,
        name: handle.name,
        permission: await permissionFor(handle),
        handle,
    };
}

export async function writeBlobToLocalBackupDirectory(filename: string, blob: Blob): Promise<string> {
    const state = await getLocalBackupDirectoryState();

    if (! state.handle) {
        throw new Error('Choose a local backup folder first.');
    }

    const permissionHandle = state.handle as PermissionCapableDirectoryHandle;
    const permission = typeof permissionHandle.requestPermission === 'function'
        ? await permissionHandle.requestPermission({ mode: 'readwrite' })
        : await permissionFor(state.handle);

    if (permission !== 'granted') {
        throw new Error('CreditSoft could not get write access to the selected backup folder.');
    }

    const fileHandle = await state.handle.getFileHandle(filename, { create: true });
    const writable = await fileHandle.createWritable();

    await writable.write(blob);
    await writable.close();

    return `${state.handle.name}/${filename}`;
}
