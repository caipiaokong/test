<template>
  <VCard id="project-list">
    <VCardText class="d-flex align-center flex-wrap gap-2 py-4">
      <!-- Create Project -->
      <VBtn
        @click="CreateProject"
      >
        创建项目
      </VBtn>
    </VCardText>

    <VDivider />

    <!-- SECTION Table -->
    <VTable>
      <!-- Table head -->
      <thead>
        <tr>
          <th>
            <VCheckbox v-model="selectAll" @change="handleSelectAllChange"/>
            <VBtn
              icon
              variant="plain"
              color="default"
              size="x-small"
              @click="handleDeleteAll"
              v-show="anySelected"
              class="trash-button"
            >
              <VIcon :size="22" icon="tabler-trash" />
            </VBtn>
          </th>
          <th>名称</th>
          <th>描述</th>
          <th>唯一ID</th>
          <th>状态</th>
          <th>创建日期</th>
          <th>操作</th>
        </tr>
      </thead>

      <!-- Table Body -->
      <tbody>
        <tr
          v-for="project in projects"
          :key="project.id"
        >
          <td>
            <VCheckbox
              v-model="project.selected"
            />
          </td>
          <td>{{ project.name }}</td>
          <td>{{ project.description }}</td>
          <td>{{ project.unique_id }}</td>
          <td>
            <VSwitch
              v-model:value="project.status"
              inset
              :label="`Switch: ${project.status ? '开启' : '关闭'}`"
              @change="() => changeStatus(project)"
            />
          </td>
          <td>{{ project.created_at }}</td>
          <td>
            <VBtn
              icon
              variant="plain"
              color="default"
              size="x-small"
              @click="openEditModal(project)"
            >
              <VIcon
                icon="tabler-pencil"
                :size="22"
              />
            </VBtn>

            <VBtn
  icon
  variant="plain"
  color="default"
  size="x-small"
  @click="viewProjectDetails(project.unique_id)"
>
  <VIcon
    :size="22"
    icon="tabler-eye"
  />
</VBtn>

            <VBtn
              icon
              variant="plain"
              color="default"
              size="x-small"
              @click="handleDelete(project)"
            >
              <VIcon
                :size="22"
                icon="tabler-trash"
              />
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>
    <!-- !SECTION -->

    <VDivider />

    <!-- SECTION Pagination -->
    <VCardText class="d-flex align-center flex-wrap justify-space-between gap-4 py-3">
      <!-- Pagination meta -->
      <span class="text-sm text-disabled">{{ paginationData }}</span>

      <!-- Pagination -->
      <VPagination
        v-model="currentPage"
        size="small"
        :total-visible="5"
        :length="totalPage"
        @next="selectedRows = []"
        @prev="selectedRows = []"
      />
    </VCardText>
    <!-- !SECTION -->

    <VAlert
      v-model="showAlert"
      :type="alertType"
      dismissible
    >
      {{ alertMessage }}
    </VAlert>
  </VCard>
</template>

<script setup>
import { getUserProjects, deleteProject, updateProject, getProjectCallbackData } from '@/api/auth'
import { ref, onMounted, reactive, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();
const projects = ref([]);
const showAlert = ref(false);
const alertType = ref(null);
const alertMessage = ref("");
const selectAll = ref(false);
const projectCallbackData = ref(null);
const currentPage = ref(1);
const totalPage = ref(1);

onMounted(async () => {
  const route = useRoute(); 
  const accessToken = sessionStorage.getItem('access_token');
  
  const response = await getUserProjects(accessToken);
  projects.value = reactive(response.data.projects);
  projects.value.forEach(project => {
    project.selected = false;
    project.status = project.status === 1 ? true : false;
  });

  const uniqueId = route.params.unique_id;
  if (typeof uniqueId !== 'undefined') {
    const callbackDataResponse = await getProjectCallbackData(uniqueId, accessToken);
    projectCallbackData.value = callbackDataResponse.data;
  }
});

const viewProjectDetails = (unique_id) => {
  router.push({ name: 'Project', params: { unique_id } });
};

const changeStatus = async (project) => {
  const newStatus = project.status ? 0 : 1;
  try {
    const response = await updateProject(project.unique_id, { status: newStatus }, sessionStorage.getItem('access_token'));
    if(response.data.message === "Project updated successfully") {
      project.status = newStatus === 1 ? true : false;
    } else {
      throw new Error(response.data.message);
    }
  } catch (error) {
    console.error("更新状态失败:", error);
  }
};

const viewProject = (unique_id) => {
  router.push({ name: 'Project', params: { unique_id } });
};

const handleDelete = async (project) => {
  const response = await deleteProject(project.unique_id, sessionStorage.getItem('access_token'));
  if(response.data.message === "Project deleted successfully") {
    alertType.value = "success";
    alertMessage.value = "项目成功删除";
    showAlert.value = true;
    setTimeout(() => {
      showAlert.value = false;
    }, 2000);
    projects.value = projects.value.filter(p => p.id !== project.id);
  } else {
    alertType.value = "error";
    alertMessage.value = "删除项目时出错";
    showAlert.value = true;
    setTimeout(() => {
      showAlert.value = false;
    }, 2000);
  }
};

const handleSelectAllChange = () => {
  projects.value.forEach(project => {
    project.selected = selectAll.value;
  });
};

const handleDeleteAll = async () => {
  const selectedProjects = projects.value.filter(project => project.selected);
  for (const project of selectedProjects) {
    await handleDelete(project);
  }
};

const anySelected = computed(() => {
  return projects.value.some(project => project.selected);
});

const CreateProject = () => {
  router.push({ name: 'CreateProject' });
};
</script>

<style lang="scss">
#invoice-list {
  .invoice-list-status {
    inline-size: 11rem;
  }

  .invoice-list-search {
    inline-size: 12rem;
  }
  .trash-button {
  position: absolute;
  right: 0;
  }
}
</style>
