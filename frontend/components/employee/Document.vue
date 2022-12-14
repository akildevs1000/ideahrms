<template>
  <div class="mt-8">
    <div class="text-center ma-2">
      <v-snackbar v-model="snackbar" top="top" color="secondary" elevation="24">
        {{ response }}
      </v-snackbar>
    </div>
    <v-dialog v-model="documents" max-width="500px">
      <v-card>
        <v-card-actions>
          <span class="headline">{{ caps(`documents`) }}</span>
          <v-spacer></v-spacer>
          <v-btn dark class="primary" fab @click="addDocumentInfo" x-small>
            <v-icon>mdi-plus</v-icon>
          </v-btn>
        </v-card-actions>
        <v-card-text>
          <v-container>
            <v-form ref="form" method="post" v-model="valid" lazy-validation>
              <v-row v-for="(d, index) in Document.items" :key="index">
                <v-col cols="5">
                  <v-text-field
                    v-model="d.title"
                    :rules="TitleRules"
                    label="Title"
                  ></v-text-field>
                  <span
                    v-if="errors && errors.title"
                    class="text-danger mt-2"
                    >{{ errors.title[0] }}</span
                  >
                </v-col>
                <v-col cols="5">
                  <div class="form-group">
                    <v-file-input
                      v-model="d.file"
                      placeholder="Upload your file"
                      label="Attachment"
                      :rules="FileRules"
                    >
                      <template v-slot:selection="{ text }">
                        <v-chip v-if="text" small label color="primary">
                          {{ text }}
                        </v-chip>
                      </template>
                    </v-file-input>

                    <span
                      v-if="errors && errors.attachment"
                      class="text-danger mt-2"
                      >{{ errors.attachment[0] }}</span
                    >
                  </div>
                </v-col>
                <v-col cols="2">
                  <div class="form-group">
                    <v-btn
                      dark
                      class="error mt-5"
                      fab
                      @click="removeItem(index)"
                      x-small
                    >
                      <v-icon>mdi-delete</v-icon>
                    </v-btn>
                  </div>
                </v-col>
              </v-row>
            </v-form>
          </v-container>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn class="error" small @click="close_document_info">
            Cancel
          </v-btn>
          <v-btn
            :disabled="!Document.items.length"
            class="primary"
            small
            @click="save_document_info"
            >Save</v-btn
          >
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-row class="pl-1 mt-5 mb-5">
      <v-col cols="12" class="text-right" style="margin: -8px">
        <v-icon
          v-if="can(`employee_document_edit_access`)"
          @click="documents = true"
          small
          class="grey"
          style="border-radius: 50%; padding: 5px"
          color="secondary"
          >mdi-plus</v-icon
        >
      </v-col>
      <v-col cols="12">
        <v-row v-for="(d, index) in document_list" :key="index" class="pa-2">
          <v-col cols="5">
            <span>{{ d.title }}</span>
          </v-col>
          <v-col cols="4">
            <a :href="d.attachment" target="_blank">
              <v-btn x-small class="primary"> open file </v-btn>
            </a>
            <v-icon color="error" @click="delete_document(d.id)">
              mdi-delete
            </v-icon>
          </v-col>
        </v-row>
      </v-col>
    </v-row>
  </div>
</template>

<script>
export default {
  props: ["document_list", "employeeId"],
  data() {
    return {
      snackbar: false,
      valid: true,
      documents: false,
      response: "",
      errors: [],
      FileRules: [
        value =>
          !value ||
          value.size < 200000 ||
          "File size should be less than 200 KB!"
      ],
      TitleRules: [v => !!v || "Title is required"],
      Document: {
        items: [{ title: "", file: "" }]
      }
    };
  },
  methods: {
    can(item) {
      return true;
    },
    caps(str) {
      if (str == "" || str == null) {
        return "---";
      } else {
        let res = str.toString();
        return res.replace(/\b\w/g, c => c.toUpperCase());
      }
    },

    addDocumentInfo() {
      this.Document.items.push({
        title: "",
        file: ""
      });
    },

    save_document_info() {
      if (!this.$refs.form.validate()) {
        alert("Enter required fields!");
        return;
      }

      let options = {
        headers: {
          "Content-Type": "multipart/form-data"
        }
      };
      let payload = new FormData();

      this.Document.items.forEach(e => {
        payload.append(`items[][title]`, e.title);
        payload.append(`items[][file]`, e.file || {});
      });

      payload.append(`company_id`, this.$auth?.user?.company?.id);
      payload.append(`employee_id`, this.employeeId);

      this.$axios
        .post(`documentinfo`, payload, options)
        .then(({ data }) => {
          this.loading = false;

          if (!data.status) {
            this.errors = data.errors;
          } else {
            this.errors = [];
            this.snackbar = true;
            this.response = data.message;
            this.getDocumentInfo(this.employeeId);
            this.Document.items = [{ title: "", file: "" }];
            this.close_document_info();
          }
        })
        .catch(e => console.log(e));
    },

    getDocumentInfo(id) {
      this.$axios.get(`documentinfo/${id}`).then(({ data }) => {
        this.document_list = data;
        this.documents = false;
      });
    },

    close_document_info() {
      this.documents = false;
      this.errors = [];
    },

    removeItem(index) {
      this.Document.items.splice(index, 1);
    },

    delete_document(id) {
      confirm(
        "Are you sure you wish to delete , to mitigate any inconvenience in future."
      ) &&
        this.$axios
          .delete(`documentinfo/${id}`)
          .then(({ data }) => {
            this.loading = false;

            if (!data.status) {
              this.errors = data.errors;
            } else {
              this.errors = [];
              this.snackbar = true;
              this.response = data.message;
              this.getDocumentInfo(this.employeeId);
              this.close_document_info();
            }
          })
          .catch(e => console.log(e));
    }
  }
};
</script>

<style scoped>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td,
th {
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #fbfdff;
}
</style>
