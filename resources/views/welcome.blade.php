<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WeatherBot - Asistente del Clima</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>

<script>

const activeConversation = computed(() => {
  return conversations.value.find(c => c.id === activeConversationId.value);
});

onMounted(async () => {
  await loadConversations();
});

async function loadConversations() {
  try {
    const response = await fetch('/api/conversations');
    const data = await response.json();
    conversations.value = data.conversations;
  } catch (err) {
    error.value = 'Error al cargar las conversaciones';
  }
}

async function selectConversation(conversationId) {
  try {
    loading.value = true;
    activeConversationId.value = conversationId;
    
    const response = await fetch(`/api/conversations/${conversationId}`);
    const data = await response.json();
    messages.value = data.messages;
  } catch (err) {
    error.value = 'Error al cargar la conversación';
  } finally {
    loading.value = false;
  }
}

async function createConversation() {
  try {
    const response = await fetch('/api/conversations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    });
    
    const data = await response.json();
    conversations.value.unshift(data.conversation);
    activeConversationId.value = data.conversation.id;
    messages.value = [];
  } catch (err) {
    error.value = 'Error al crear la conversación';
  }
}

async function deleteConversation(conversationId) {
  try {
    await fetch(`/api/conversations/${conversationId}`, {
      method: 'DELETE',
    });
    
    conversations.value = conversations.value.filter(c => c.id !== conversationId);
    
    if (activeConversationId.value === conversationId) {
      activeConversationId.value = null;
      messages.value = [];
    }
  } catch (err) {
    error.value = 'Error al eliminar la conversación';
  }
}

async function sendMessage(content) {
  if (!content.trim() || loading.value) return;
  
  try {
    loading.value = true;
    
    const response = await fetch('/api/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conversation_id: activeConversationId.value,
        content: content.trim(),
      }),
    });
    
    if (!response.ok) {
      throw new Error('Error al enviar el mensaje');
    }
    
    const data = await response.json();
    messages.value.push(data.user_message);
    messages.value.push(data.assistant_message);
    
    const conversationIndex = conversations.value.findIndex(
      c => c.id === activeConversationId.value
    );
    if (conversationIndex !== -1) {
      const conversation = conversations.value[conversationIndex];
      conversation.last_message_at = new Date().toISOString();
      conversation.preview = data.assistant_message.content;
      conversations.value.splice(conversationIndex, 1);
      conversations.value.unshift(conversation);
    }
  } catch (err) {
    error.value = err.message || 'Error al enviar el mensaje';
  } finally {
    loading.value = false;
  }
}
</script>
