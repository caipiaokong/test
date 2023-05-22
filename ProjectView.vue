<template>
  <VCard v-if="projects">
    <VCardItem class="project-header d-flex flex-wrap justify-space-between py-4 gap-4">
      <VCardTitle>Projects (Total: {{ projects.total }})</VCardTitle>

      <template #append>
        <div class="d-flex align-center gap-2" style="width: 272px;">
          <VLabel>Search:</VLabel>
          <VTextField v-model="searchQuery" placeholder="Search" />
        </div>
      </template>
    </VCardItem>

    <VDivider />

    <!-- SECTION Table -->
    <VTable class="text-no-wrap">
      <!-- 👉 Table head -->
      <thead>
        <tr>
          <!-- 👉 Check/Uncheck all checkbox -->
          <th scope="col" class="text-center">
            <div style="width: 1rem;">
              <VCheckbox
                v-model="selectAllProject"
                @click="selectUnselectAll"
              />
            </div>
          </th>

          <th scope="col" class="font-weight-semibold">
            内容
          </th>
          <th scope="col" class="font-weight-semibold">
            服务器内容
          </th>
          <th scope="col" class="font-weight-semibold">
            域名
          </th>
          <th scope="col" class="font-weight-semibold">
            用户
          </th>
          <th scope="col" class="font-weight-semibold">
            创建日期
          </th>
          <th scope="col" class="font-weight-semibold">
            图片
          </th>
          <th scope="col" class="font-weight-semibold">
            操作
          </th>
        </tr>
      </thead>

      <!-- 👉 Table Body -->
      <tbody>
        <tr v-for="project in filteredProjects" :key="project.id">
          <!-- 👉 Individual checkbox -->
          <td>
            <div style="width: 1rem;">
              <VCheckbox
                :id="`check${project.id}`"
                :model-value="isSelected(project.id)"
                @click="toggleSelection(project.id)"
              />
            </div>
          </td>

          <!-- 👉 内容 -->
          <td class="content-cell" @click="handleContentClick(project.content)">{{ filteredContent(project.content) }}</td>

          <!-- 👉 服务器内容 -->
          <td @click="handleServerContentClick(project.serverContent)">{{ filteredServerContent(project.serverContent) }}</td>

          <!-- 👉 域名 -->
          <td>{{ project.domain }}</td>

          <!-- 👉 用户 -->
          <td>{{ project.user_id }}</td>

          <!-- 👉 创建日期 -->
          <td>{{ project.created_at }}</td>

          <!-- 👉 图片 -->
          <td>
            <template v-if="project.img">
              <button @click="toggleImage(project.img)">
                查看图片
              </button>
              <div
                v-if="showImage && currentImage === project.img"
                class="image-modal"
                @click="toggleImage(null)"
              >
                <div class="image-modal-content">
                  <img :src="project.img" /> <!-- 直接使用 Base64 图片数据 -->
                </div>
              </div>
            </template>
            <span v-else>无图片</span>
          </td>

          <!-- 👉 操作 -->
          <td>
            <VBtn
              icon
              variant="plain"
              color="default"
              size="x-small"
              @click="handleDelete(project)"
            >
              <VIcon size="22" icon="mdi-delete" />
            </VBtn>
          </td>
        </tr>
      </tbody>

<!-- 👉 table footer  -->
<tfoot v-show="isLoading">
  <tr>
    <td colspan="8" class="text-center text-body-1">
      <VProgressCircular indeterminate color="primary" />
    </td>
  </tr>
</tfoot>

<tfoot v-show="!filteredProjects.length && !isLoading">
  <tr>
    <td colspan="8" class="text-center text-body-1">
      No data available
    </td>
  </tr>
</tfoot>

    </VTable>

    <!-- 👉 Show delete all button if any project is selected -->
    <VBtn
  variant="outlined"
  v-if="showDeleteButton" 
  @click="showModal = true"
>
删除数据
</VBtn>


<v-dialog v-model="showModal" max-width="500px">
  <v-card>
    <v-card-title class="text-h5">删除确认</v-card-title>
    <v-card-text>是否确实要删除所有选定的项目?</v-card-text>
    <v-card-actions>
      <v-spacer></v-spacer>
      <v-btn color="blue darken-1" text @click="showModal = false">取消</v-btn>
      <v-btn color="blue darken-1" text @click="handleDeleteAll">确定</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>

    <!-- !SECTION -->

    <VDivider />

    <!-- SECTION Pagination -->
    <VCardText class="d-flex align-center flex-wrap justify-space-between gap-4 py-3">
      <!-- 👉 Pagination meta -->
      <span class="text-sm text-disabled">Page {{ projects.current_page }} of {{ projects.last_page }}</span>

      <!-- 👉 Pagination -->
      <VPagination
        v-model="currentPage"
        size="small"
        :total-visible="2"
        :length="projects.last_page"
        @update:modelValue="handlePageChange"
      />
    </VCardText>
    <!-- !SECTION -->

    <!-- 👉 Alert -->
    <VAlert
      v-model="showAlert"
      :type="alertType"
      :dismissible="true"
      :class="{ 'mt-4': showAlert }"
    >
      {{ alertMessage }}
    </VAlert>
    <!-- 在这里添加你的Modal -->
<Modal v-if="contentModal" @close="contentModal = false">
  <p>{{ modalContent }}</p>
</Modal>
  </VCard>

  <VProgressCircular
    v-else
    indeterminate
    color="success"
  />
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { getProjectCallbackData, deleteCallbackData } from '@/api/auth';
import { useRouter, useRoute } from 'vue-router';

const projects = ref([]);
const selectedRows = ref([]);
const selectAllProject = ref(false);
const showImage = ref(false);
const currentImage = ref(null);
const searchQuery = ref('');
const currentPage = ref(1);
const totalPage = ref(1);
const paginationData = computed(() => `Page ${currentPage.value} of ${totalPage.value}`);
const showAlert = ref(false);
const alertType = ref('');
const alertMessage = ref('');
const showModal = ref(false);
const modalContent = ref('');
const router = useRouter();
const route = useRoute();
const showDeleteIcon = ref(false);
const isLoading = ref(true);
const contentModal = ref(false); // Added
const serverContentModal = ref(false); // Added

const handleContentClick = (content) => {
  modalContent.value = content;
  contentModal.value = true;
};

const handleServerContentClick = (serverContent) => {
  modalContent.value = serverContent;
  serverContentModal.value = true;
};


const filteredContent = (content) => {
  // 如果内容长度大于100，那么截取前100个字符并加上 "..."
  // 否则，直接返回原内容
  return content.length > 100 ? content.substring(0, 50) + '...' : content;
};

const filteredServerContent = (serverContent) => {
  // 如果内容长度大于100，那么截取前100个字符并加上 "..."
  // 否则，直接返回原内容
  return serverContent.length > 100 ? serverContent.substring(0, 50) + '...' : serverContent;
};

const handlePageChange = async (Page) => {
  try {
    currentPage.value = Page;  // 更新当前页码
    const uniqueId = route.params.unique_id;
    const accessToken = sessionStorage.getItem('access_token');
    
    console.log(`Request to handlePageChange: uniqueId=${uniqueId}, accessToken=${accessToken}, Page=${Page}`);
    
    const response = await getProjectCallbackData(uniqueId, accessToken, Page);  // 使用新的页码请求数据
    console.log('Response from handlePageChange:', response);

    projects.value = response.data;
    totalPage.value = response.last_page;

    // 你可以在这里直接更新 filteredProjects
    filteredProjects.value = response.data;
  } catch (error) {
    console.error('获取回调数据失败', error);
  }
};

// 获取回调数据
const getCallbackData = async () => {
  try {
    const uniqueId = route.params.unique_id;
    const accessToken = sessionStorage.getItem('access_token');

    console.log(`Request to getCallbackData: uniqueId=${uniqueId}, accessToken=${accessToken}`);
    
    const response = await getProjectCallbackData(uniqueId, accessToken);
    console.log('Response from getCallbackData:', response);

    projects.value = response.data;
    totalPage.value = response.last_page;
    isLoading.value = false; // 新增
  } catch (error) {
    console.error('获取回调数据失败', error);
    isLoading.value = false; // 新增
  }
};

onMounted(async () => {
  try {
    console.log('On Mounted...');
    await getCallbackData();
  } catch (error) {
    console.error('获取回调数据时出错:', error);
  }
});

const filteredProjects = computed(() => {
  if (projects.value && projects.value.data) {
    if (searchQuery.value) {
      return projects.value.data.filter((project) => {
        const content = getContent(project.content);
        const serverContent = getServerContent(project.serverContent);
        const domain = project.domain;
        const userId = project.user_id;
        const createdAt = project.created_at;

        return (
          content.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
          serverContent.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
          domain.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
          (userId && userId.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
          createdAt.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
      });
    } else {
      return projects.value.data;
    }
  } else {
    return [];
  }
});


// 计算属性，检查是否有项目被选中
const showDeleteButton = computed(() => {
  return selectedRows.value && selectedRows.value.length > 0;
});

// 更新 selectUnselectAll 方法
const selectUnselectAll = () => {
  if (selectAllProject.value) {
    selectedRows.value = [];
  } else if (projects.value.data) {
    selectedRows.value = projects.value.data.map((project) => project.id);
  }
};

// Check if a row is selected
const isSelected = (id) => {
  return selectedRows.value.includes(id);
};

// Toggle selection of a row
const toggleSelection = (id) => {
  const index = selectedRows.value.indexOf(id);
  if (index > -1) {
    selectedRows.value.splice(index, 1);
  } else {
    selectedRows.value.push(id);
  }
};

const handleDelete = (project) => {
  console.log('Deleting project:', project);

  const accessToken = sessionStorage.getItem('access_token');
  console.log('Access token:', accessToken);

  deleteCallbackData(project.id, accessToken)
    .then(() => {
      console.log('Delete success');
      showAlert.value = true;
      alertType.value = 'success';
      alertMessage.value = '删除成功';

      // 删除成功后，更新显示的数据列表
      projects.value.data = projects.value.data.filter((item) => item.id !== project.id); 
      projects.value.total -= 1; // 减少项目总数

      console.log('Updated projects:', projects.value);

      // 2秒后隐藏提示消息
      setTimeout(() => {
        showAlert.value = false;
      }, 2000);
    })
    .catch((error) => {
      console.error('删除失败', error);
      showAlert.value = true;
      alertType.value = 'error';
      alertMessage.value = '删除失败';

      // 2秒后隐藏提示消息
      setTimeout(() => {
        showAlert.value = false;
      }, 2000);
    });
};

const handleDeleteAll = async () => {
  try {
    const accessToken = sessionStorage.getItem('access_token');
    
    console.log('Deleting projects:', selectedRows.value);
    console.log('Access token:', accessToken);

    await deleteCallbackData(selectedRows.value, accessToken);
    
    console.log('Delete success');
    showAlert.value = true;
    alertType.value = 'success';
    alertMessage.value = '删除成功';

    // 删除成功后，从列表中移除这些项目
    projects.value.data = projects.value.data.filter((item) => !selectedRows.value.includes(item.id));
    projects.value.total -= selectedRows.value.length; // 减少项目总数

    console.log('Updated projects:', projects.value);

    // 清空选中项目列表
    selectedRows.value = [];
    showModal.value = false;
    selectAllProject.value = false;

    // 2秒后隐藏提示消息
    setTimeout(() => {
      showAlert.value = false;
    }, 2000);
  } catch (error) {
    console.error('删除失败', error);
    showAlert.value = true;
    alertType.value = 'error';
    alertMessage.value = '删除失败';

    // 2秒后隐藏提示消息
    setTimeout(() => {
      showAlert.value = false;
    }, 2000);
  }
};


const toggleImage = (image) => {
  showImage.value = !showImage.value;
  currentImage.value = image;
};

</script>


<style scoped>
.image-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.image-modal-content {
  max-width: 80%;
  max-height: 80%;
}

.image-modal img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.content-cell {
    max-width: 200px; /* 可以根据你的需要调整这个值 */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
</style>
