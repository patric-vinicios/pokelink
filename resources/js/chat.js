document.addEventListener('alpine:init', () => {
    Alpine.store('presence', {
        onlineIds: new Set(),
    });

    Alpine.store('realtime', {
        connected: true,
    });

    if (! window.Echo) {
        return;
    }

    window.Echo.join('online')
        .here((users) => Alpine.store('presence').onlineIds = new Set(users.map((user) => user.id)))
        .joining((user) => Alpine.store('presence').onlineIds.add(user.id))
        .leaving((user) => Alpine.store('presence').onlineIds.delete(user.id));

    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        Alpine.store('realtime').connected = states.current === 'connected';
    });
});
