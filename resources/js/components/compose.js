// Merge Alpine component parts while keeping their getters as getters
// (object spread would evaluate them once and freeze the value).
export function compose(...parts) {
    const target = {};
    for (const part of parts) {
        Object.defineProperties(target, Object.getOwnPropertyDescriptors(part));
    }
    return target;
}
